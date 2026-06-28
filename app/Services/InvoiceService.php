<?php

namespace App\Services;

use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Exceptions\RevenueWorkflowException;
use App\Models\Deal;
use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Invoice::query()
            ->with(['branch', 'deal.lead'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['deal_id'] ?? null, fn ($q, $v) => $q->where('deal_id', $v))
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where('code', 'like', "%{$v}%"))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('issued_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('issued_at', '<=', $v))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Tạo invoice (status=draft) từ Deal. Snapshot toàn bộ amount tại thời điểm tạo.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Deal $deal, array $data): Invoice
    {
        return DB::transaction(function () use ($deal, $data) {
            if (! ($deal->stage instanceof DealStage)) {
                throw new RevenueWorkflowException('Deal stage không hợp lệ.');
            }
            if ($deal->stage === DealStage::ClosedLost) {
                throw new RevenueWorkflowException(
                    'Không thể phát hành hoá đơn cho deal đã mất.'
                );
            }
            if ((int) $deal->total_amount <= 0) {
                throw new RevenueWorkflowException(
                    'Deal chưa có sản phẩm. Hãy thêm sản phẩm trước khi tạo hoá đơn.'
                );
            }

            $authUser = Auth::user();

            return Invoice::create([
                'branch_id' => $deal->branch_id,
                'deal_id' => $deal->id,
                'created_by' => $authUser?->id,
                'code' => $this->generateCode($deal->branch_id),
                'subtotal_amount' => (int) $deal->subtotal_amount,
                'tax_amount' => (int) $deal->tax_amount,
                'total_amount' => (int) $deal->total_amount,
                'paid_amount' => 0,
                'status' => InvoiceStatus::Draft->value,
                'issued_at' => null,
                'due_at' => $data['due_at'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
        });
    }

    /**
     * Cập nhật invoice ở trạng thái draft. Sau khi issued không cho sửa số tiền.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            if ($invoice->status !== InvoiceStatus::Draft) {
                // Sau issued chỉ cho sửa note, due_at.
                $data = array_intersect_key($data, array_flip(['note', 'due_at']));
            }

            unset(
                $data['branch_id'], $data['deal_id'], $data['code'],
                $data['created_by'], $data['issued_by'], $data['voided_by'],
                $data['status'], $data['issued_at'], $data['voided_at'],
                $data['paid_amount'], $data['subtotal_amount'],
                $data['tax_amount'], $data['total_amount'],
            );

            $invoice->update($data);

            return $invoice->fresh();
        });
    }

    public function delete(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw new RevenueWorkflowException(
                    'Chỉ xoá được hoá đơn ở trạng thái nháp. Hãy huỷ thay vì xoá.'
                );
            }
            $invoice->delete();
        });
    }

    /**
     * Phát hành invoice (Draft → Issued). Re-snapshot từ Deal nếu deal có thay đổi.
     */
    public function issue(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw new RevenueWorkflowException(
                    'Chỉ hoá đơn nháp mới có thể phát hành.'
                );
            }

            $deal = $invoice->deal()->withoutGlobalScopes()->firstOrFail();

            if ($deal->stage instanceof DealStage && $deal->stage === DealStage::ClosedLost) {
                throw new RevenueWorkflowException(
                    'Deal đã mất, không thể phát hành hoá đơn.'
                );
            }
            if ((int) $deal->total_amount <= 0) {
                throw new RevenueWorkflowException(
                    'Deal hiện không có giá trị. Không thể phát hành hoá đơn.'
                );
            }

            $authUser = Auth::user();

            $invoice->update([
                'subtotal_amount' => (int) $deal->subtotal_amount,
                'tax_amount' => (int) $deal->tax_amount,
                'total_amount' => (int) $deal->total_amount,
                'status' => InvoiceStatus::Issued->value,
                'issued_at' => now()->toDateString(),
                'issued_by' => $authUser?->id,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Huỷ invoice. Chỉ cho phép khi chưa có payment đã xác nhận.
     */
    public function void(Invoice $invoice, ?string $reason = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason) {
            if ($invoice->status === InvoiceStatus::Void) {
                throw new RevenueWorkflowException('Hoá đơn đã bị huỷ.');
            }
            if ($invoice->status === InvoiceStatus::Paid) {
                throw new RevenueWorkflowException(
                    'Không thể huỷ hoá đơn đã thanh toán đầy đủ.'
                );
            }

            $confirmedPayments = $invoice->payments()
                ->withoutGlobalScopes()
                ->whereNotNull('confirmed_at')
                ->exists();

            if ($confirmedPayments) {
                throw new RevenueWorkflowException(
                    'Hoá đơn đã có thanh toán đã xác nhận. Hãy hoàn tiền trước khi huỷ.'
                );
            }

            $authUser = Auth::user();

            $invoice->update([
                'status' => InvoiceStatus::Void->value,
                'voided_at' => now(),
                'voided_by' => $authUser?->id,
                'void_reason' => $reason,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Đồng bộ paid_amount + status dựa trên các payment đã xác nhận.
     * Service Payment gọi sau mỗi lần create/confirm/delete.
     */
    public function syncFromPayments(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            // Hoá đơn đã void thì giữ nguyên trạng thái.
            if ($invoice->status === InvoiceStatus::Void) {
                return $invoice->fresh();
            }

            $confirmedSum = (int) $invoice->payments()
                ->withoutGlobalScopes()
                ->whereNotNull('confirmed_at')
                ->sum('amount');

            $total = (int) $invoice->total_amount;
            $newStatus = $invoice->status;

            if ($invoice->status === InvoiceStatus::Draft) {
                // Payment chỉ được tạo trên invoice đã issued, nhưng vẫn an toàn.
                $invoice->update(['paid_amount' => $confirmedSum]);

                return $invoice->fresh();
            }

            if ($confirmedSum >= $total && $total > 0) {
                $newStatus = InvoiceStatus::Paid;
            } elseif ($confirmedSum > 0) {
                $newStatus = InvoiceStatus::PartiallyPaid;
            } else {
                // Quay về Issued/Overdue dựa trên due_at.
                $newStatus = $invoice->due_at !== null && $invoice->due_at->isPast()
                    ? InvoiceStatus::Overdue
                    : InvoiceStatus::Issued;
            }

            // Đè Overdue nếu vẫn còn balance và quá hạn.
            if (
                $newStatus !== InvoiceStatus::Paid
                && $invoice->due_at !== null
                && $invoice->due_at->isPast()
                && ($total - $confirmedSum) > 0
            ) {
                $newStatus = InvoiceStatus::Overdue;
            }

            $invoice->update([
                'paid_amount' => $confirmedSum,
                'status' => $newStatus,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Cron-style: refresh status overdue cho mọi invoice đang issued/partial mà quá hạn.
     */
    public function refreshOverdueStatus(): int
    {
        $count = 0;
        Invoice::withoutGlobalScopes()
            ->whereIn('status', [
                InvoiceStatus::Issued->value,
                InvoiceStatus::PartiallyPaid->value,
            ])
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', now()->toDateString())
            ->chunkById(100, function ($invoices) use (&$count) {
                foreach ($invoices as $invoice) {
                    if (($invoice->total_amount - $invoice->paid_amount) > 0) {
                        $invoice->update(['status' => InvoiceStatus::Overdue->value]);
                        $count++;
                    }
                }
            });

        return $count;
    }

    /** Sinh code `INV-{branchId}-{YYMMDD}-{seq}`. */
    private function generateCode(int $branchId): string
    {
        $datePart = now()->format('ymd');
        $countToday = Invoice::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return sprintf('INV-%d-%s-%03d', $branchId, $datePart, $countToday + 1);
    }
}
