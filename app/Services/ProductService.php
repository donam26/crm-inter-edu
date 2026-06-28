<?php

namespace App\Services;

use App\Exceptions\RevenueWorkflowException;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Product::query()
            ->with('branch')
            ->when(
                array_key_exists('is_active', $filters)
                    && $filters['is_active'] !== null
                    && $filters['is_active'] !== '',
                fn ($q) => $q->where(
                    'is_active',
                    filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)
                )
            )
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(
                fn ($q2) => $q2->where('name', 'like', "%{$v}%")
                    ->orWhere('code', 'like', "%{$v}%")
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            // branch_id từ auth (super-admin có thể truyền branch_id nhưng
            // ở v1 ta cũng buộc theo auth — nếu super-admin tạo: phải chọn branch
            // qua dropdown, FormRequest cho phép field branch_id; nếu null thì
            // fallback về auth user branch).
            $authUser = Auth::user();

            if ($authUser?->hasRole('super-admin')) {
                if (empty($data['branch_id'])) {
                    throw new RevenueWorkflowException(
                        'Super-admin phải chọn chi nhánh khi tạo sản phẩm.'
                    );
                }
            } else {
                $data['branch_id'] = $authUser?->branch_id;
            }

            $data['is_active'] = (bool) ($data['is_active'] ?? true);

            return Product::create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            // Chặn override branch_id qua input.
            unset($data['branch_id']);

            $product->update($data);

            return $product->fresh();
        });
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            if ($product->dealItems()->withoutGlobalScopes()->exists()) {
                throw new RevenueWorkflowException(
                    'Không thể xoá sản phẩm đang được sử dụng trong deal.'
                );
            }

            $product->delete();
        });
    }
}
