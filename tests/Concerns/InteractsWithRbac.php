<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hỗ trợ RBAC multi-tenant (teams) cho test:
 *  - Seed toàn bộ permission catalog (global).
 *  - makeUser(): tạo user + role hệ thống đúng team (branch) với quyền mặc định.
 *  - makeRole(): tạo role tùy chỉnh trong một branch.
 *
 * Team context được set theo branch trước khi findOrCreate role / assignRole để
 * role được đóng dấu branch_id đúng. Trong request HTTP, middleware
 * SetPermissionsTeamFromBranch sẽ tự set lại team theo user đăng nhập.
 */
trait InteractsWithRbac
{
    protected function setUpRbac(): void
    {
        // Placeholder route `login` để middleware auth có chỗ redirect cho guest.
        $router = $this->app['router'];
        if (! $router->has('login')) {
            $router->get('/login', fn () => response('login', 200))->name('login');
            $router->getRoutes()->refreshNameLookups();
        }

        foreach (PermissionCatalog::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    /**
     * Tạo user gắn role hệ thống (super-admin/branch-manager/sales) trong team
     * của branch tương ứng (super-admin → team null/global).
     */
    protected function makeUser(string $role, ?Branch $branch = null): User
    {
        $branchId = $branch?->id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($branchId);

        $roleModel = Role::findOrCreate($role, 'web');
        $roleModel->forceFill(['is_system' => true])->save();
        $roleModel->syncPermissions($this->defaultPermissionsForRole($role));

        $user = User::factory()->create(['branch_id' => $branchId]);
        $user->assignRole($roleModel);

        // Spatie teams: quan hệ `roles` được lọc theo team hiện hành lúc nạp.
        // Instance này được dùng lại qua actingAs ở các test có nhiều team, nên
        // ta cố định team đúng rồi nạp sẵn quan hệ để tránh lazy-load dưới team
        // sai (mỗi request thật luôn resolve user mới nên không gặp vấn đề này).
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($branchId);
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->load('roles', 'permissions');

        return $user;
    }

    /**
     * Tạo role tùy chỉnh (mặc định non-system) trong một branch với tập quyền cho trước.
     *
     * @param  list<string>  $permissions
     */
    protected function makeRole(string $name, ?Branch $branch, array $permissions = [], bool $system = false): Role
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch?->id);

        $role = Role::findOrCreate($name, 'web');
        $role->forceFill(['is_system' => $system])->save();
        $role->syncPermissions($permissions);

        return $role;
    }

    /**
     * @return list<string>
     */
    protected function defaultPermissionsForRole(string $role): array
    {
        return match ($role) {
            'super-admin' => PermissionCatalog::all(),
            'branch-manager' => PermissionCatalog::branchAssignable(),
            'sales' => RolePermissionSeeder::salesPermissions(),
            default => [],
        };
    }
}
