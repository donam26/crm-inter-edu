<?php

namespace App\Services;

use App\Exceptions\BranchHasDependenciesException;
use App\Models\Branch;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BranchService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Branch::query()
            ->when(
                array_key_exists('is_active', $filters)
                    && $filters['is_active'] !== null
                    && $filters['is_active'] !== '',
                fn ($q) => $q->where(
                    'is_active',
                    filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)
                )
            )
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(
                fn ($q2) => $q2->where('name', 'like', "%{$v}%")
                    ->orWhere('code', 'like', "%{$v}%")
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    public function create(array $data): Branch
    {
        return DB::transaction(fn () => Branch::create($data));
    }

    public function update(Branch $branch, array $data): Branch
    {
        return DB::transaction(function () use ($branch, $data) {
            $branch->update($data);

            return $branch->fresh();
        });
    }

    public function delete(Branch $branch): void
    {
        DB::transaction(function () use ($branch) {
            $hasLeads = class_exists(Lead::class) && $branch->leads()->exists();
            $hasDeals = class_exists(Deal::class)
                && Deal::withoutGlobalScopes()->where('branch_id', $branch->id)->exists();
            $hasInvoices = class_exists(Invoice::class)
                && Invoice::withoutGlobalScopes()->where('branch_id', $branch->id)->exists();
            $hasPayments = class_exists(Payment::class)
                && Payment::withoutGlobalScopes()->where('branch_id', $branch->id)->exists();
            $hasProducts = class_exists(Product::class)
                && Product::withoutGlobalScopes()->where('branch_id', $branch->id)->exists();

            if (
                $branch->users()->exists()
                || $hasLeads
                || $hasDeals
                || $hasInvoices
                || $hasPayments
                || $hasProducts
            ) {
                throw new BranchHasDependenciesException(
                    'Không thể xóa branch đang có user, lead, sản phẩm, deal, hoá đơn hoặc thanh toán liên kết.'
                );
            }

            $branch->delete();
        });
    }
}
