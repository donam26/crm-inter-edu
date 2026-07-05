<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed permission + role mặc định cho RBAC multi-tenant.
     *
     * - Permission là GLOBAL (không thuộc team).
     * - `super-admin`: role toàn cục (branch_id null) — mọi quyền.
     * - Mỗi branch có `branch-manager` + `sales` riêng (team = branch_id),
     *   đánh dấu is_system để khóa sửa/xóa qua UI. Branch tự tạo thêm role khác.
     *
     * Idempotent: chạy lại an toàn. Phải chạy SAU BranchSeeder.
     */
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        // 1. Toàn bộ permission (global).
        foreach (PermissionCatalog::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // 2. Super-admin toàn cục (team null) với mọi quyền.
        $registrar->setPermissionsTeamId(null);
        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $superAdmin->forceFill(['is_system' => true])->save();
        $superAdmin->syncPermissions(PermissionCatalog::all());

        // 3. Role mặc định cho từng branch (team = branch_id).
        $managerPermissions = PermissionCatalog::branchAssignable();
        $salesPermissions = self::salesPermissions();

        foreach (Branch::all() as $branch) {
            $registrar->setPermissionsTeamId($branch->id);

            $manager = Role::findOrCreate('branch-manager', 'web');
            $manager->forceFill(['is_system' => true])->save();
            $manager->syncPermissions($managerPermissions);

            $sales = Role::findOrCreate('sales', 'web');
            $sales->forceFill(['is_system' => true])->save();
            $sales->syncPermissions($salesPermissions);
        }

        // Reset về team toàn cục để các seeder sau không kế thừa team.
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    /**
     * Bộ quyền mặc định của 'sales': thao tác nghiệp vụ trên bản ghi CỦA MÌNH
     * (không có *.view-all), không quản lý người dùng/vai trò, không thao tác
     * tài chính nhạy cảm (xóa/hủy hóa đơn, xóa thanh toán).
     *
     * Public để test harness tái sử dụng đúng tập quyền (single source of truth).
     *
     * @return list<string>
     */
    public static function salesPermissions(): array
    {
        return [
            'dashboard.view',
            'customers.view', 'customers.create', 'customers.update',
            'contacts.view', 'contacts.create', 'contacts.update', 'contacts.delete',
            'activities.view', 'activities.create', 'activities.update', 'activities.delete',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete',
            'labels.view',
            'events.view', 'events.create', 'events.update', 'events.delete',
            'products.view',
            'deals.view', 'deals.create', 'deals.update', 'deals.close',
            'invoices.view', 'invoices.create', 'invoices.issue',
            'payments.view', 'payments.create', 'payments.confirm',
            'revenues.view',
        ];
    }
}
