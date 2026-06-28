<?php

namespace Tests\Unit;

use App\Exceptions\RoleInUseException;
use App\Exceptions\RoleIsSystemException;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * Unit tests cho RoleService.
 *
 * Phạm vi:
 *  - create đóng dấu branch_id theo actor (manager → branch mình, super-admin → null)
 *  - create lọc bỏ permission actor không được phép gán (branches.* với manager)
 *  - update ném RoleIsSystemException khi role hệ thống
 *  - delete ném RoleInUseException khi role đang được gán cho user
 *  - delete thành công với role tùy chỉnh chưa dùng
 */
class RoleServiceTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    private RoleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RoleService;
        $this->setUpRbac();
    }

    /**
     * Tên permission gắn cho role (đếm trực tiếp, độc lập team context).
     *
     * @return list<string>
     */
    private function rolePermissionNames(Role $role): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->fresh()->permissions->pluck('name')->all();
    }

    // ───────────────────── create: branch_id ─────────────────────

    public function test_create_sets_branch_id_from_manager_actor(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $role = $this->service->create($mgr, [
            'name' => 'Telesales',
            'permissions' => ['leads.view'],
        ]);

        $this->assertSame($branch->id, $role->branch_id);
        $this->assertFalse($role->is_system);
    }

    public function test_create_sets_null_branch_id_for_super_admin(): void
    {
        $admin = $this->makeUser('super-admin');

        $role = $this->service->create($admin, [
            'name' => 'Auditor',
            'permissions' => ['dashboard.view'],
        ]);

        $this->assertNull($role->branch_id);
    }

    // ───────────────────── create: permission stripping ─────────────────────

    public function test_create_strips_non_assignable_permissions_for_manager(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        // branches.create là quyền toàn cục → manager không được gán, phải bị loại.
        $role = $this->service->create($mgr, [
            'name' => 'Sneaky',
            'permissions' => ['leads.view', 'branches.create'],
        ]);

        $names = $this->rolePermissionNames($role);
        $this->assertContains('leads.view', $names);
        $this->assertNotContains('branches.create', $names);
    }

    public function test_create_allows_super_admin_to_assign_global_permissions(): void
    {
        $admin = $this->makeUser('super-admin');

        $role = $this->service->create($admin, [
            'name' => 'Branch Admin',
            'permissions' => ['branches.create', 'branches.view'],
        ]);

        $names = $this->rolePermissionNames($role);
        $this->assertContains('branches.create', $names);
        $this->assertContains('branches.view', $names);
    }

    // ───────────────────── update: system role guard ─────────────────────

    public function test_update_throws_for_system_role(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $systemRole = Role::where('name', 'branch-manager')
            ->where('branch_id', $branch->id)->firstOrFail();

        $this->expectException(RoleIsSystemException::class);
        $this->service->update($mgr, $systemRole, [
            'name' => 'Renamed',
            'permissions' => ['leads.view'],
        ]);
    }

    public function test_update_changes_name_and_permissions_for_custom_role(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $role = $this->service->create($mgr, [
            'name' => 'Custom',
            'permissions' => ['leads.view'],
        ]);

        $updated = $this->service->update($mgr, $role, [
            'name' => 'Custom Renamed',
            'permissions' => ['leads.view', 'tasks.view'],
        ]);

        $this->assertSame('Custom Renamed', $updated->name);
        $this->assertEqualsCanonicalizing(
            ['leads.view', 'tasks.view'],
            $this->rolePermissionNames($updated),
        );
    }

    // ───────────────────── delete ─────────────────────

    public function test_delete_throws_for_system_role(): void
    {
        $branch = Branch::factory()->create();
        $this->makeUser('branch-manager', $branch);
        $systemRole = Role::where('name', 'branch-manager')
            ->where('branch_id', $branch->id)->firstOrFail();

        $this->expectException(RoleIsSystemException::class);
        $this->service->delete($systemRole);
    }

    public function test_delete_throws_when_role_in_use(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $role = $this->service->create($mgr, [
            'name' => 'Assigned',
            'permissions' => ['leads.view'],
        ]);

        // Gán role 'Assigned' cho một user trong team của branch. Đặt lại team
        // ngay trước assignRole vì makeUser bên trong có reset team context.
        $user = User::factory()->create(['branch_id' => $branch->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->assignRole($role);

        // Precondition: role thực sự đang được gán cho user.
        $this->assertDatabaseHas('model_has_roles', ['role_id' => $role->id]);

        $this->expectException(RoleInUseException::class);
        $this->service->delete($role);
    }

    public function test_delete_succeeds_for_unused_custom_role(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $role = $this->service->create($mgr, [
            'name' => 'Unused',
            'permissions' => ['leads.view'],
        ]);

        $this->service->delete($role);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
