<?php

namespace App\Services;

use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Exceptions\RevenueWorkflowException;
use App\Models\Deal;
use App\Models\DealItem;
use App\Models\Lead;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DealService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Deal::query()
            ->with(['branch', 'lead', 'owner'])
            ->when($filters['stage'] ?? null, fn ($q, $v) => $q->where('stage', $v))
            ->when($filters['owner_user_id'] ?? null, fn ($q, $v) => $q->where('owner_user_id', $v))
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(
                fn ($q2) => $q2->where('title', 'like', "%{$v}%")
                    ->orWhere('code', 'like', "%{$v}%")
            ))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Tạo deal mới gắn với 1 Lead. Enforce 1 Lead = 1 Deal qua DB unique.
     *
     * Service-layer injection bắt buộc:
     *  - branch_id   ← từ Lead cha (không lấy từ auth, vì super-admin có
     *    thể đang tạo deal hộ branch khác)
     *  - created_by  ← Auth::id()
     *  - owner       ← input hoặc fallback về lead.assigned_user_id
     *  - amounts     ← khởi tạo 0 (sẽ recompute khi thêm item)
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Deal
    {
        return DB::transaction(function () use ($data) {
            $authUser = Auth::user();
            $lead = Lead::withoutGlobalScopes()->findOrFail($data['lead_id']);

            $this->guardLeadBranchAccess($authUser, $lead);

            // Enforce 1-Lead-1-Deal ở Service-layer (DB unique là hàng rào cuối).
            if (Deal::withoutGlobalScopes()->where('lead_id', $lead->id)->exists()) {
                throw ValidationException::withMessages([
                    'lead_id' => 'Lead này đã có deal. Mỗi lead chỉ có 1 deal.',
                ]);
            }

            // Resolve owner: input > lead.assigned_user_id > null. Nếu có, phải cùng branch.
            $ownerId = $data['owner_user_id'] ?? $lead->assigned_user_id;
            if ($ownerId !== null) {
                $owner = User::find($ownerId);
                if (! $owner || (int) $owner->branch_id !== (int) $lead->branch_id) {
                    throw ValidationException::withMessages([
                        'owner_user_id' => 'Người phụ trách phải thuộc cùng chi nhánh với lead.',
                    ]);
                }
            }

            $payload = [
                'branch_id' => $lead->branch_id,
                'lead_id' => $lead->id,
                'owner_user_id' => $ownerId,
                'created_by' => $authUser?->id,
                'code' => $this->generateCode($lead->branch_id),
                'title' => $data['title'] ?? ('Deal - '.$lead->school_name),
                'subtotal_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 0,
                'stage' => DealStage::Lead->value,
                'expected_close_date' => $data['expected_close_date'] ?? null,
                'actual_close_date' => null,
                'note' => $data['note'] ?? null,
            ];

            return Deal::create($payload);
        });
    }

    /**
     * Cập nhật deal. KHÔNG cập nhật stage qua method này — dùng `win()`/`lose()`/`reopen()`.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Deal $deal, array $data): Deal
    {
        return DB::transaction(function () use ($deal, $data) {
            // Chặn override các field auto-set / state-machine.
            unset(
                $data['branch_id'],
                $data['lead_id'],
                $data['created_by'],
                $data['code'],
                $data['stage'],
                $data['actual_close_date'],
                $data['subtotal_amount'],
                $data['tax_amount'],
                $data['total_amount'],
            );

            // Owner mới (nếu có) phải cùng branch.
            if (array_key_exists('owner_user_id', $data) && $data['owner_user_id'] !== null) {
                $owner = User::find($data['owner_user_id']);
                if (! $owner || (int) $owner->branch_id !== (int) $deal->branch_id) {
                    throw ValidationException::withMessages([
                        'owner_user_id' => 'Người phụ trách phải thuộc cùng chi nhánh với deal.',
                    ]);
                }
            }

            $deal->update($data);

            return $deal->fresh();
        });
    }

    public function delete(Deal $deal): void
    {
        DB::transaction(function () use ($deal) {
            if ($deal->invoices()->withoutGlobalScopes()->exists()) {
                throw new RevenueWorkflowException(
                    'Không thể xoá deal đã có hoá đơn. Hãy huỷ các hoá đơn trước.'
                );
            }
            $deal->delete();
        });
    }

    /**
     * Đánh dấu deal thắng. Yêu cầu có ≥ 1 item.
     * Side-effect: đồng bộ Lead.status = won, set actual_close_date = today.
     */
    public function win(Deal $deal): Deal
    {
        return DB::transaction(function () use ($deal) {
            if ($deal->stage instanceof DealStage && $deal->stage->isClosed()) {
                throw new RevenueWorkflowException(
                    'Deal đã đóng. Không thể thay đổi trạng thái.'
                );
            }

            if ($deal->items()->withoutGlobalScopes()->count() === 0) {
                throw new RevenueWorkflowException(
                    'Deal chưa có sản phẩm. Hãy thêm ít nhất một dòng trước khi đánh dấu thắng.'
                );
            }

            $deal->update([
                'stage' => DealStage::ClosedWon,
                'actual_close_date' => now()->toDateString(),
            ]);

            // Đồng bộ Lead.status = won (bypass scope vì có thể super-admin
            // đang thao tác trên branch khác).
            Lead::withoutGlobalScopes()
                ->where('id', $deal->lead_id)
                ->update(['status' => LeadStatus::Won->value]);

            return $deal->fresh();
        });
    }

    /**
     * Đánh dấu deal mất. Side-effect: đồng bộ Lead.status = lost.
     */
    public function lose(Deal $deal, ?string $reason = null): Deal
    {
        return DB::transaction(function () use ($deal, $reason) {
            if ($deal->stage instanceof DealStage && $deal->stage->isClosed()) {
                throw new RevenueWorkflowException(
                    'Deal đã đóng. Không thể thay đổi trạng thái.'
                );
            }

            $update = [
                'stage' => DealStage::ClosedLost,
                'actual_close_date' => now()->toDateString(),
            ];
            if ($reason !== null && $reason !== '') {
                $update['note'] = trim(($deal->note ? $deal->note."\n" : '').'Lý do mất: '.$reason);
            }

            $deal->update($update);

            Lead::withoutGlobalScopes()
                ->where('id', $deal->lead_id)
                ->update(['status' => LeadStatus::Lost->value]);

            return $deal->fresh();
        });
    }

    /**
     * Mở lại deal đã đóng → Negotiation. Reset actual_close_date.
     */
    public function reopen(Deal $deal): Deal
    {
        return DB::transaction(function () use ($deal) {
            if (! ($deal->stage instanceof DealStage) || ! $deal->stage->isClosed()) {
                throw new RevenueWorkflowException('Deal chưa đóng, không cần mở lại.');
            }

            $deal->update([
                'stage' => DealStage::Negotiation,
                'actual_close_date' => null,
            ]);

            return $deal->fresh();
        });
    }

    /** Đẩy deal sang stage tiếp theo trong pipeline (linear forward). */
    public function moveStage(Deal $deal, DealStage $target): Deal
    {
        return DB::transaction(function () use ($deal, $target) {
            if ($target->isClosed()) {
                throw new RevenueWorkflowException(
                    'Dùng win()/lose() để đóng deal, không dùng moveStage().'
                );
            }
            if ($deal->stage instanceof DealStage && $deal->stage->isClosed()) {
                throw new RevenueWorkflowException('Deal đã đóng.');
            }

            $deal->update(['stage' => $target]);

            return $deal->fresh();
        });
    }

    /**
     * Thêm/cập nhật line item, đồng thời recompute tổng deal.
     *
     * @param  array<string, mixed>  $data
     */
    public function addItem(Deal $deal, array $data): DealItem
    {
        return DB::transaction(function () use ($deal, $data) {
            if ($deal->stage instanceof DealStage && $deal->stage->isClosed()) {
                throw new RevenueWorkflowException(
                    'Deal đã đóng, không thể chỉnh sửa sản phẩm.'
                );
            }

            $productId = $data['product_id'] ?? null;
            $name = $data['name'] ?? null;
            $unitPrice = (int) ($data['unit_price'] ?? 0);

            if ($productId !== null) {
                $product = Product::withoutGlobalScopes()->findOrFail($productId);
                if ((int) $product->branch_id !== (int) $deal->branch_id) {
                    throw ValidationException::withMessages([
                        'product_id' => 'Sản phẩm phải thuộc cùng chi nhánh với deal.',
                    ]);
                }
                // Snapshot tên + đơn giá nếu input không cung cấp.
                $name = $name ?: $product->name;
                $unitPrice = $unitPrice > 0 ? $unitPrice : (int) $product->unit_price;
            }

            $quantity = max(1, (int) ($data['quantity'] ?? 1));
            $discount = max(0, (int) ($data['discount_amount'] ?? 0));
            $taxRate = max(0, min(100, (int) ($data['tax_rate'] ?? 0)));

            $amounts = DealItem::computeAmounts($quantity, $unitPrice, $discount, $taxRate);

            $item = $deal->items()->create([
                'branch_id' => $deal->branch_id,
                'product_id' => $productId,
                'name' => $name ?? 'Mục không tên',
                'description' => $data['description'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_rate' => $taxRate,
                ...$amounts,
                'position' => (int) ($data['position']
                    ?? ($deal->items()->withoutGlobalScopes()->max('position') + 1)),
            ]);

            $this->recomputeDealAmounts($deal);

            return $item->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateItem(DealItem $item, array $data): DealItem
    {
        return DB::transaction(function () use ($item, $data) {
            $deal = $item->deal()->withoutGlobalScopes()->firstOrFail();

            if ($deal->stage instanceof DealStage && $deal->stage->isClosed()) {
                throw new RevenueWorkflowException(
                    'Deal đã đóng, không thể chỉnh sửa sản phẩm.'
                );
            }

            unset($data['branch_id'], $data['deal_id']);

            $quantity = max(1, (int) ($data['quantity'] ?? $item->quantity));
            $unitPrice = max(0, (int) ($data['unit_price'] ?? $item->unit_price));
            $discount = max(0, (int) ($data['discount_amount'] ?? $item->discount_amount));
            $taxRate = max(0, min(100, (int) ($data['tax_rate'] ?? $item->tax_rate)));

            $amounts = DealItem::computeAmounts($quantity, $unitPrice, $discount, $taxRate);

            $item->update([
                ...$data,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_rate' => $taxRate,
                ...$amounts,
            ]);

            $this->recomputeDealAmounts($deal);

            return $item->fresh();
        });
    }

    public function removeItem(DealItem $item): void
    {
        DB::transaction(function () use ($item) {
            $deal = $item->deal()->withoutGlobalScopes()->firstOrFail();

            if ($deal->stage instanceof DealStage && $deal->stage->isClosed()) {
                throw new RevenueWorkflowException(
                    'Deal đã đóng, không thể xoá sản phẩm.'
                );
            }

            $item->delete();
            $this->recomputeDealAmounts($deal);
        });
    }

    /**
     * Đồng bộ subtotal/tax/total trên Deal từ tổng các line items.
     */
    public function recomputeDealAmounts(Deal $deal): void
    {
        $items = $deal->items()->withoutGlobalScopes()->get();

        $subtotal = $items->sum('line_subtotal');
        $tax = $items->sum('line_tax_amount');
        $total = $subtotal + $tax;

        $deal->update([
            'subtotal_amount' => (int) $subtotal,
            'tax_amount' => (int) $tax,
            'total_amount' => (int) $total,
        ]);
    }

    private function guardLeadBranchAccess(?User $authUser, Lead $lead): void
    {
        if ($authUser === null) {
            return;
        }
        if ($authUser->hasRole('super-admin')) {
            return;
        }
        if ((int) $authUser->branch_id !== (int) $lead->branch_id) {
            throw ValidationException::withMessages([
                'lead_id' => 'Lead không thuộc chi nhánh của bạn.',
            ]);
        }
    }

    /** Sinh code dạng `D-{branchId}-{YYMMDD}-{seq}`. */
    private function generateCode(int $branchId): string
    {
        $datePart = now()->format('ymd');
        $countToday = Deal::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return sprintf('D-%d-%s-%03d', $branchId, $datePart, $countToday + 1);
    }
}
