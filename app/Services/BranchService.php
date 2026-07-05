<?php

namespace App\Services;

use App\Exceptions\BranchHasDependenciesException;
use App\Models\Branch;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

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
        return DB::transaction(function () use ($data) {
            $branch = Branch::create($data);

            // Cấp bộ vai trò hệ thống mặc định cho chi nhánh mới để nó hoạt động
            // như các chi nhánh seed sẵn ở màn hình Vai trò / gán người dùng.
            // Thiếu bước này, chi nhánh tạo qua UI sẽ không có vai trò nào.
            $this->provisionSystemRoles($branch);

            return $branch;
        });
    }

    /**
     * Tạo bộ vai trò hệ thống mặc định (branch-manager + sales) cho một chi
     * nhánh, giống RolePermissionSeeder. Đóng dấu team context = branch->id để
     * Spatie gán role đúng branch, rồi khôi phục team context của request.
     * Idempotent nhờ Role::findOrCreate.
     */
    private function provisionSystemRoles(Branch $branch): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        try {
            $registrar->setPermissionsTeamId($branch->id);

            $manager = Role::findOrCreate('branch-manager', 'web');
            $manager->forceFill(['is_system' => true])->save();
            $manager->syncPermissions(PermissionCatalog::branchAssignable());

            $sales = Role::findOrCreate('sales', 'web');
            $sales->forceFill(['is_system' => true])->save();
            $sales->syncPermissions(RolePermissionSeeder::salesPermissions());
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
            $registrar->forgetCachedPermissions();
        }
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
            $hasCustomers = class_exists(Customer::class) && $branch->customers()->exists();
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
                || $hasCustomers
                || $hasDeals
                || $hasInvoices
                || $hasPayments
                || $hasProducts
            ) {
                throw new BranchHasDependenciesException(
                    'Không thể xóa branch đang có user, customer, sản phẩm, deal, hoá đơn hoặc thanh toán liên kết.'
                );
            }

            $branch->delete();
        });
    }
}
