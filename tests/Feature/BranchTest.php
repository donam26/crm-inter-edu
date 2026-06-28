<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRbac();
    }

    // ───────────────────── guest ─────────────────────

    public function test_guest_is_redirected_to_login_from_index(): void
    {
        $this->get(route('branches.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_from_show(): void
    {
        $branch = Branch::factory()->create();

        $this->get(route('branches.show', $branch))
            ->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_from_store(): void
    {
        $this->post(route('branches.store'), [
            'name' => 'X', 'code' => 'BR-G1',
        ])->assertRedirect(route('login'));
    }

    // ───────────────────── super-admin ─────────────────────

    public function test_super_admin_can_view_index(): void
    {
        $admin = $this->makeUser('super-admin');
        Branch::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('branches.index'))
            ->assertOk();
    }

    public function test_super_admin_can_view_any_branch(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();

        $this->actingAs($admin)
            ->get(route('branches.show', $branch))
            ->assertOk();
    }

    public function test_super_admin_can_store_branch(): void
    {
        $admin = $this->makeUser('super-admin');

        $payload = [
            'name' => 'Hà Nội Campus',
            'code' => 'BR-HN01',
            'address' => '123 Cầu Giấy',
            'phone' => '0240000000',
            'is_active' => true,
        ];

        $response = $this->actingAs($admin)
            ->post(route('branches.store'), $payload);

        $this->assertDatabaseHas('branches', ['code' => 'BR-HN01', 'name' => 'Hà Nội Campus']);
        $created = Branch::where('code', 'BR-HN01')->firstOrFail();
        $response->assertRedirect(route('branches.show', $created));
    }

    public function test_super_admin_can_update_branch(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create(['code' => 'BR-OLD']);

        $this->actingAs($admin)
            ->put(route('branches.update', $branch), [
                'name' => 'Updated Name',
                'code' => 'BR-NEW',
                'is_active' => true,
            ])
            ->assertRedirect(route('branches.show', $branch));

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'code' => 'BR-NEW',
            'name' => 'Updated Name',
        ]);
    }

    public function test_super_admin_can_destroy_branch(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();

        $this->actingAs($admin)
            ->delete(route('branches.destroy', $branch))
            ->assertRedirect(route('branches.index'));

        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    // ───────────────────── branch-manager ─────────────────────

    public function test_branch_manager_cannot_view_index(): void
    {
        // branches.* là quyền toàn cục (super-admin). Branch-manager không có
        // branches.view nên bị chặn khỏi danh sách chi nhánh.
        $own = Branch::factory()->create();
        $manager = $this->makeUser('branch-manager', $own);

        $this->actingAs($manager)
            ->get(route('branches.index'))
            ->assertForbidden();
    }

    public function test_branch_manager_can_view_only_own_branch(): void
    {
        $own = Branch::factory()->create();
        $manager = $this->makeUser('branch-manager', $own);

        $this->actingAs($manager)
            ->get(route('branches.show', $own))
            ->assertOk();
    }

    public function test_branch_manager_cannot_view_other_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $manager = $this->makeUser('branch-manager', $own);

        $this->actingAs($manager)
            ->get(route('branches.show', $other))
            ->assertForbidden();
    }

    public function test_branch_manager_cannot_store_branch(): void
    {
        $own = Branch::factory()->create();
        $manager = $this->makeUser('branch-manager', $own);

        $this->actingAs($manager)
            ->post(route('branches.store'), [
                'name' => 'X', 'code' => 'BR-NEW2', 'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('branches', ['code' => 'BR-NEW2']);
    }

    public function test_branch_manager_cannot_update_branch(): void
    {
        $own = Branch::factory()->create(['code' => 'BR-OWN']);
        $manager = $this->makeUser('branch-manager', $own);

        $this->actingAs($manager)
            ->put(route('branches.update', $own), [
                'name' => 'X', 'code' => 'BR-X', 'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('branches', ['id' => $own->id, 'code' => 'BR-OWN']);
    }

    public function test_branch_manager_cannot_destroy_branch(): void
    {
        $own = Branch::factory()->create();
        $manager = $this->makeUser('branch-manager', $own);

        $this->actingAs($manager)
            ->delete(route('branches.destroy', $own))
            ->assertForbidden();

        $this->assertDatabaseHas('branches', ['id' => $own->id]);
    }

    // ───────────────────── sales ─────────────────────

    public function test_sales_cannot_view_index(): void
    {
        // branches.* là quyền toàn cục (super-admin). Sales không có
        // branches.view nên bị chặn khỏi danh sách chi nhánh.
        $own = Branch::factory()->create();
        $sales = $this->makeUser('sales', $own);

        $this->actingAs($sales)
            ->get(route('branches.index'))
            ->assertForbidden();
    }

    public function test_sales_can_view_only_own_branch(): void
    {
        $own = Branch::factory()->create();
        $sales = $this->makeUser('sales', $own);

        $this->actingAs($sales)
            ->get(route('branches.show', $own))
            ->assertOk();
    }

    public function test_sales_cannot_view_other_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $sales = $this->makeUser('sales', $own);

        $this->actingAs($sales)
            ->get(route('branches.show', $other))
            ->assertForbidden();
    }

    public function test_sales_cannot_store_branch(): void
    {
        $own = Branch::factory()->create();
        $sales = $this->makeUser('sales', $own);

        $this->actingAs($sales)
            ->post(route('branches.store'), [
                'name' => 'X', 'code' => 'BR-S1', 'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('branches', ['code' => 'BR-S1']);
    }

    public function test_sales_cannot_update_branch(): void
    {
        $own = Branch::factory()->create(['code' => 'BR-S']);
        $sales = $this->makeUser('sales', $own);

        $this->actingAs($sales)
            ->put(route('branches.update', $own), [
                'name' => 'X', 'code' => 'BR-S2', 'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('branches', ['id' => $own->id, 'code' => 'BR-S']);
    }

    public function test_sales_cannot_destroy_branch(): void
    {
        $own = Branch::factory()->create();
        $sales = $this->makeUser('sales', $own);

        $this->actingAs($sales)
            ->delete(route('branches.destroy', $own))
            ->assertForbidden();

        $this->assertDatabaseHas('branches', ['id' => $own->id]);
    }

    // ───────────────────── code uniqueness ─────────────────────

    public function test_store_rejects_duplicate_code(): void
    {
        $admin = $this->makeUser('super-admin');
        Branch::factory()->create(['code' => 'BR-DUP']);

        $this->actingAs($admin)
            ->from(route('branches.create'))
            ->post(route('branches.store'), [
                'name' => 'Another',
                'code' => 'BR-DUP',
                'is_active' => true,
            ])
            ->assertRedirect(route('branches.create'))
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Branch::where('code', 'BR-DUP')->count());
    }

    public function test_update_rejects_duplicate_code(): void
    {
        $admin = $this->makeUser('super-admin');
        Branch::factory()->create(['code' => 'BR-AAA']);
        $target = Branch::factory()->create(['code' => 'BR-BBB']);

        $this->actingAs($admin)
            ->from(route('branches.edit', $target))
            ->put(route('branches.update', $target), [
                'name' => 'X',
                'code' => 'BR-AAA',
                'is_active' => true,
            ])
            ->assertRedirect(route('branches.edit', $target))
            ->assertSessionHasErrors('code');

        $this->assertDatabaseHas('branches', ['id' => $target->id, 'code' => 'BR-BBB']);
    }

    // ───────────────────── refusal-if-linked ─────────────────────

    public function test_destroy_fails_when_branch_has_users(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();

        // Tạo một user thuộc branch này (ngoài super-admin đã tạo, super-admin
        // có branch_id=null nên không tính).
        User::factory()->create(['branch_id' => $branch->id]);

        $this->actingAs($admin)
            ->from(route('branches.show', $branch))
            ->delete(route('branches.destroy', $branch))
            ->assertRedirect(route('branches.show', $branch))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('branches', ['id' => $branch->id]);
    }
}
