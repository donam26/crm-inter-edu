<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRbac();
    }

    /**
     * Số permission đang gắn cho role (đếm trực tiếp ở bảng pivot, không phụ
     * thuộc team context vì permission là global).
     */
    private function rolePermissionNames(Role $role): array
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        return $role->fresh()->permissions->pluck('name')->all();
    }

    // ───────────────────── guest ─────────────────────

    public function test_guest_redirected_from_roles_index(): void
    {
        $this->get(route('roles.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_redirected_from_roles_store(): void
    {
        $this->post(route('roles.store'), [])
            ->assertRedirect(route('login'));
    }

    // ───────────────────── super-admin ─────────────────────

    public function test_super_admin_can_view_index(): void
    {
        $admin = $this->makeUser('super-admin');

        $this->actingAs($admin)
            ->get(route('roles.index'))
            ->assertOk();
    }

    public function test_super_admin_can_create_global_role(): void
    {
        $admin = $this->makeUser('super-admin');

        $this->actingAs($admin)
            ->post(route('roles.store'), [
                'name' => 'Auditor',
                'permissions' => ['dashboard.view', 'revenues.view'],
            ])
            ->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'Auditor')->first();
        $this->assertNotNull($role);
        // super-admin tạo role toàn cục → branch_id null.
        $this->assertNull($role->branch_id);
        $this->assertFalse($role->is_system);
        $this->assertEqualsCanonicalizing(
            ['dashboard.view', 'revenues.view'],
            $this->rolePermissionNames($role),
        );
    }

    public function test_super_admin_can_create_role_with_global_permission(): void
    {
        $admin = $this->makeUser('super-admin');

        // super-admin được phép gán cả quyền toàn cục branches.*.
        $this->actingAs($admin)
            ->post(route('roles.store'), [
                'name' => 'Branch Admin',
                'permissions' => ['branches.view', 'branches.create'],
            ])
            ->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'Branch Admin')->first();
        $this->assertNotNull($role);
        $this->assertContains('branches.create', $this->rolePermissionNames($role));
    }

    public function test_super_admin_can_edit_custom_role(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $role = $this->makeRole('Custom', $branch, ['leads.view']);

        $this->actingAs($admin)
            ->put(route('roles.update', $role), [
                'name' => 'Custom Renamed',
                'permissions' => ['leads.view', 'leads.create'],
            ])
            ->assertRedirect(route('roles.index'));

        $role->refresh();
        $this->assertSame('Custom Renamed', $role->name);
        $this->assertEqualsCanonicalizing(
            ['leads.view', 'leads.create'],
            $this->rolePermissionNames($role),
        );
    }

    public function test_super_admin_can_delete_custom_role(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $role = $this->makeRole('Disposable', $branch, ['leads.view']);

        $this->actingAs($admin)
            ->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_super_admin_cannot_delete_system_role(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        // makeUser tạo role hệ thống branch-manager (is_system=true) trong branch.
        $this->makeUser('branch-manager', $branch);
        $systemRole = Role::where('name', 'branch-manager')
            ->where('branch_id', $branch->id)->firstOrFail();

        // super-admin bypass policy, nhưng RoleService chặn xóa role hệ thống.
        $this->actingAs($admin)
            ->from(route('roles.index'))
            ->delete(route('roles.destroy', $systemRole))
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $systemRole->id]);
    }

    // ───────────────────── branch-manager: own branch CRUD ─────────────────────

    public function test_branch_manager_can_view_index(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $this->actingAs($mgr)
            ->get(route('roles.index'))
            ->assertOk();
    }

    public function test_branch_manager_can_create_custom_role_in_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $this->actingAs($mgr)
            ->post(route('roles.store'), [
                'name' => 'Telesales',
                'permissions' => ['leads.view', 'leads.create', 'contacts.view'],
            ])
            ->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'Telesales')->first();
        $this->assertNotNull($role);
        // Service đóng dấu branch_id của manager.
        $this->assertSame($branch->id, $role->branch_id);
        $this->assertFalse($role->is_system);
        $this->assertEqualsCanonicalizing(
            ['leads.view', 'leads.create', 'contacts.view'],
            $this->rolePermissionNames($role),
        );
    }

    public function test_branch_manager_can_edit_custom_role_in_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $role = $this->makeRole('Editable', $branch, ['leads.view']);

        $this->actingAs($mgr)
            ->put(route('roles.update', $role), [
                'name' => 'Editable Updated',
                'permissions' => ['leads.view', 'tasks.view'],
            ])
            ->assertRedirect(route('roles.index'));

        $role->refresh();
        $this->assertSame('Editable Updated', $role->name);
        $this->assertEqualsCanonicalizing(
            ['leads.view', 'tasks.view'],
            $this->rolePermissionNames($role),
        );
    }

    public function test_branch_manager_can_delete_custom_role_in_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $role = $this->makeRole('Trash', $branch, ['leads.view']);

        $this->actingAs($mgr)
            ->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    // ───────────────────── branch-manager: system roles immutable ─────────────────────

    public function test_branch_manager_cannot_edit_system_role(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $systemRole = Role::where('name', 'branch-manager')
            ->where('branch_id', $branch->id)->firstOrFail();

        $this->actingAs($mgr)
            ->put(route('roles.update', $systemRole), [
                'name' => 'Hacked Name',
                'permissions' => ['leads.view'],
            ])
            ->assertForbidden();

        $systemRole->refresh();
        $this->assertSame('branch-manager', $systemRole->name);
    }

    public function test_branch_manager_cannot_delete_system_role(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        // makeUser('branch-manager') seeds branch-manager; seed sales too.
        $this->makeUser('sales', $branch);
        $systemRole = Role::where('name', 'sales')
            ->where('branch_id', $branch->id)->firstOrFail();

        $this->actingAs($mgr)
            ->delete(route('roles.destroy', $systemRole))
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $systemRole->id]);
    }

    // ───────────────────── branch-manager: cross-branch denied ─────────────────────

    public function test_branch_manager_cannot_edit_role_from_other_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $foreignRole = $this->makeRole('Foreign Custom', $other, ['leads.view']);

        $this->actingAs($mgr)
            ->put(route('roles.update', $foreignRole), [
                'name' => 'Stolen',
                'permissions' => ['leads.view'],
            ])
            ->assertForbidden();

        $foreignRole->refresh();
        $this->assertSame('Foreign Custom', $foreignRole->name);
    }

    public function test_branch_manager_cannot_delete_role_from_other_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $foreignRole = $this->makeRole('Foreign Disposable', $other, ['leads.view']);

        $this->actingAs($mgr)
            ->delete(route('roles.destroy', $foreignRole))
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $foreignRole->id]);
    }

    // ───────────────────── branch-manager: global perm rejected ─────────────────────

    public function test_branch_manager_cannot_attach_global_permission(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        // branches.* phải tồn tại như permission để mô phỏng cố tình gửi lên.
        Permission::findOrCreate('branches.create', 'web');

        $this->actingAs($mgr)
            ->from(route('roles.index'))
            ->post(route('roles.store'), [
                'name' => 'Sneaky Global',
                'permissions' => ['leads.view', 'branches.create'],
            ])
            ->assertSessionHasErrors('permissions.1');

        // Quyền toàn cục không bao giờ gắn được vào role của branch-manager.
        $role = Role::where('name', 'Sneaky Global')->first();
        if ($role !== null) {
            $this->assertNotContains('branches.create', $this->rolePermissionNames($role));
        } else {
            $this->assertDatabaseMissing('roles', ['name' => 'Sneaky Global']);
        }
    }

    // ───────────────────── sales: no roles.* perms ─────────────────────

    public function test_sales_cannot_view_roles_index(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);

        $this->actingAs($sales)
            ->get(route('roles.index'))
            ->assertForbidden();
    }

    public function test_sales_cannot_store_role(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);

        $this->actingAs($sales)
            ->post(route('roles.store'), [
                'name' => 'Nope',
                'permissions' => ['leads.view'],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'Nope']);
    }

    // ───────────────────── validation ─────────────────────

    public function test_store_requires_name(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $this->actingAs($mgr)
            ->from(route('roles.index'))
            ->post(route('roles.store'), [
                'permissions' => ['leads.view'],
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_store_rejects_duplicate_name_within_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        // Tạo role đầu tiên qua đúng luồng ứng dụng (RoleService) để mô phỏng
        // dữ liệu thực; sau đó tên trùng phải bị rule unique chặn.
        app(RoleService::class)->create($mgr, ['name' => 'Duplicate', 'permissions' => ['leads.view']]);

        $this->actingAs($mgr)
            ->from(route('roles.index'))
            ->post(route('roles.store'), [
                'name' => 'Duplicate',
                'permissions' => ['leads.view'],
            ])
            ->assertSessionHasErrors('name');

        // Vẫn chỉ có một role 'Duplicate' trong branch.
        $this->assertSame(
            1,
            Role::where('name', 'Duplicate')->where('branch_id', $branch->id)->count(),
        );
    }
}
