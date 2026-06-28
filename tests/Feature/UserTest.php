<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class UserTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRbac();
    }

    // ───────────────────── guest ─────────────────────

    public function test_guest_redirected_from_users_index(): void
    {
        $this->get(route('users.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_redirected_from_users_store(): void
    {
        $this->post(route('users.store'), [])
            ->assertRedirect(route('login'));
    }

    // ───────────────────── super-admin ─────────────────────

    public function test_super_admin_can_view_index(): void
    {
        $admin = $this->makeUser('super-admin');

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk();
    }

    public function test_super_admin_can_create_super_admin_without_branch(): void
    {
        $admin = $this->makeUser('super-admin');

        $payload = [
            'name' => 'New Admin',
            'email' => 'new-admin@x.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => ['super-admin'],
        ];

        $this->actingAs($admin)
            ->post(route('users.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'new-admin@x.com']);

        $u = User::where('email', 'new-admin@x.com')->first();
        $this->assertNull($u->branch_id);
        $this->assertTrue($u->hasRole('super-admin'));
    }

    public function test_super_admin_can_create_branch_manager_with_branch(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        // Đảm bảo role branch-manager tồn tại trong team của branch.
        $this->makeUser('branch-manager', $branch);

        $payload = [
            'name' => 'Mgr',
            'email' => 'mgr@x.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => ['branch-manager'],
            'branch_id' => $branch->id,
        ];

        $this->actingAs($admin)
            ->post(route('users.store'), $payload)
            ->assertRedirect();

        $u = User::where('email', 'mgr@x.com')->first();
        $this->assertNotNull($u);
        $this->assertSame($branch->id, $u->branch_id);
        $this->assertContains('branch-manager', $u->roles->pluck('name')->all());
    }

    public function test_super_admin_can_assign_multiple_roles(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $this->makeUser('branch-manager', $branch);
        $this->makeUser('sales', $branch);

        $payload = [
            'name' => 'Multi',
            'email' => 'multi@x.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => ['branch-manager', 'sales'],
            'branch_id' => $branch->id,
        ];

        $this->actingAs($admin)
            ->post(route('users.store'), $payload)
            ->assertRedirect();

        $u = User::where('email', 'multi@x.com')->first();
        $this->assertNotNull($u);
        $names = $u->roles->pluck('name')->all();
        $this->assertContains('branch-manager', $names);
        $this->assertContains('sales', $names);
    }

    public function test_super_admin_can_assign_roles_from_any_branch(): void
    {
        $admin = $this->makeUser('super-admin');
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $this->makeUser('sales', $branchA);
        $this->makeUser('sales', $branchB);

        // Super-admin gán role của branchB cho user thuộc branchB.
        $payload = [
            'name' => 'Cross',
            'email' => 'cross@x.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => ['sales'],
            'branch_id' => $branchB->id,
        ];

        $this->actingAs($admin)
            ->post(route('users.store'), $payload)
            ->assertRedirect();

        $u = User::where('email', 'cross@x.com')->first();
        $this->assertNotNull($u);
        $this->assertSame($branchB->id, $u->branch_id);
        $this->assertContains('sales', $u->roles->pluck('name')->all());
    }

    public function test_super_admin_can_update_user_roles(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $u = $this->makeUser('sales', $branch);
        $this->makeUser('branch-manager', $branch);

        $this->actingAs($admin)
            ->put(route('users.update', $u), [
                'name' => $u->name,
                'email' => $u->email,
                'roles' => ['branch-manager'],
                'branch_id' => $branch->id,
            ])
            ->assertRedirect();

        $u->refresh();
        $names = $u->fresh(['roles'])->roles->pluck('name')->all();
        $this->assertContains('branch-manager', $names);
        $this->assertNotContains('sales', $names);
    }

    public function test_super_admin_can_delete_user(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $u = $this->makeUser('sales', $branch);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $u))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $u->id]);
    }

    public function test_super_admin_can_view_any_user(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $u = $this->makeUser('sales', $branch);

        $this->actingAs($admin)
            ->get(route('users.show', $u))
            ->assertOk();
    }

    // ───────────────────── branch-manager: own branch ─────────────────────

    public function test_branch_manager_can_view_index(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $this->actingAs($mgr)
            ->get(route('users.index'))
            ->assertOk();
    }

    public function test_branch_manager_can_create_user_in_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $this->makeUser('sales', $branch); // seed sales role in branch team

        $this->actingAs($mgr)
            ->post(route('users.store'), [
                'name' => 'New Sales',
                'email' => 'newsales@x.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => ['sales'],
            ])
            ->assertRedirect();

        $u = User::where('email', 'newsales@x.com')->first();
        $this->assertNotNull($u);
        // UserService ép branch_id về branch của manager.
        $this->assertSame($branch->id, $u->branch_id);
        $this->assertContains('sales', $u->roles->pluck('name')->all());
    }

    public function test_branch_manager_is_forced_to_own_branch_ignoring_input(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $this->makeUser('sales', $own);

        // Cố gửi branch_id của branch khác — phải bị ép về branch của manager.
        $this->actingAs($mgr)
            ->post(route('users.store'), [
                'name' => 'Forced',
                'email' => 'forced@x.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => ['sales'],
                'branch_id' => $other->id,
            ])
            ->assertRedirect();

        $u = User::where('email', 'forced@x.com')->first();
        $this->assertNotNull($u);
        $this->assertSame($own->id, $u->branch_id);
    }

    public function test_branch_manager_cannot_assign_super_admin_role(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $this->makeUser('sales', $branch);
        // Tạo super-admin để role toàn cục (team null) tồn tại trong DB → vượt
        // qua rule `exists`, buộc UserService phải tự loại bỏ ở tầng service.
        $this->makeUser('super-admin');

        // super-admin là role toàn cục (team null) — không thuộc branch của
        // manager, nên UserService loại bỏ trước khi sync; chỉ còn 'sales'.
        $this->actingAs($mgr)
            ->post(route('users.store'), [
                'name' => 'Sneaky',
                'email' => 'sneaky@x.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => ['sales', 'super-admin'],
            ])
            ->assertRedirect();

        $u = User::where('email', 'sneaky@x.com')->first();
        $this->assertNotNull($u);
        $names = $u->roles->pluck('name')->all();
        $this->assertContains('sales', $names);
        $this->assertNotContains('super-admin', $names);
        $this->assertFalse($u->hasRole('super-admin'));
    }

    public function test_branch_manager_can_view_own_branch_user(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $other = $this->makeUser('sales', $branch);

        $this->actingAs($mgr)
            ->get(route('users.show', $other))
            ->assertOk();
    }

    public function test_branch_manager_can_update_own_branch_user(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $other = $this->makeUser('sales', $branch);

        $this->actingAs($mgr)
            ->put(route('users.update', $other), [
                'name' => 'Updated Name',
                'email' => $other->email,
                'roles' => ['sales'],
            ])
            ->assertRedirect();

        $other->refresh();
        $this->assertSame('Updated Name', $other->name);
    }

    public function test_branch_manager_can_delete_own_branch_user(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $other = $this->makeUser('sales', $branch);

        $this->actingAs($mgr)
            ->delete(route('users.destroy', $other))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }

    // ───────────────────── branch-manager: other branch forbidden ─────────────────────

    public function test_branch_manager_cannot_view_other_branch_user(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $foreign = $this->makeUser('sales', $other);

        $this->actingAs($mgr)
            ->get(route('users.show', $foreign))
            ->assertForbidden();
    }

    public function test_branch_manager_cannot_update_other_branch_user(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $foreign = $this->makeUser('sales', $other);

        $this->actingAs($mgr)
            ->put(route('users.update', $foreign), [
                'name' => 'Hacked',
                'email' => $foreign->email,
                'roles' => ['sales'],
            ])
            ->assertForbidden();
    }

    public function test_branch_manager_cannot_delete_other_branch_user(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $foreign = $this->makeUser('sales', $other);

        $this->actingAs($mgr)
            ->delete(route('users.destroy', $foreign))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $foreign->id]);
    }

    // ───────────────────── sales: forbidden ─────────────────────

    public function test_sales_cannot_access_users_index(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);

        $this->actingAs($sales)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_sales_cannot_access_users_create(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);

        $this->actingAs($sales)
            ->get(route('users.create'))
            ->assertForbidden();
    }

    public function test_sales_cannot_store_user(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);

        $this->actingAs($sales)
            ->post(route('users.store'), [
                'name' => 'X',
                'email' => 'z@x.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => ['sales'],
                'branch_id' => $branch->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'z@x.com']);
    }

    public function test_sales_cannot_delete_user(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $other = $this->makeUser('sales', $branch);

        $this->actingAs($sales)
            ->delete(route('users.destroy', $other))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    // ───────────────────── validation ─────────────────────

    public function test_roles_required_on_create(): void
    {
        $admin = $this->makeUser('super-admin');

        $this->actingAs($admin)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'No Roles',
                'email' => 'noroles@x.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                // no roles
            ])
            ->assertSessionHasErrors('roles');

        $this->assertDatabaseMissing('users', ['email' => 'noroles@x.com']);
    }

    public function test_email_must_be_unique_on_create(): void
    {
        $admin = $this->makeUser('super-admin');
        User::factory()->create(['email' => 'taken@x.com']);

        $this->actingAs($admin)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'X',
                'email' => 'taken@x.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => ['super-admin'],
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_email_uniqueness_ignores_self_on_update(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $u = $this->makeUser('sales', $branch);

        $this->actingAs($admin)
            ->put(route('users.update', $u), [
                'name' => 'New Name',
                'email' => $u->email, // unchanged
                'roles' => ['sales'],
                'branch_id' => $branch->id,
            ])
            ->assertRedirect();

        $u->refresh();
        $this->assertSame('New Name', $u->name);
    }

    public function test_password_optional_on_update(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $u = $this->makeUser('sales', $branch);
        $originalHash = $u->password;

        $this->actingAs($admin)
            ->put(route('users.update', $u), [
                'name' => 'Updated',
                'email' => $u->email,
                'roles' => ['sales'],
                'branch_id' => $branch->id,
                // no password
            ])
            ->assertRedirect();

        $u->refresh();
        $this->assertSame($originalHash, $u->password);
    }
}
