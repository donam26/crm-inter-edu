<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRbac();
    }

    // ───────────────────── guest ─────────────────────

    public function test_guest_redirected_from_leads_index(): void
    {
        $this->get(route('customers.index'))
            ->assertRedirect(route('login'));
    }

    // ───────────────────── super-admin ─────────────────────

    public function test_super_admin_can_view_index_across_branches(): void
    {
        $admin = $this->makeUser('super-admin');
        $b1 = Branch::factory()->create();
        $b2 = Branch::factory()->create();
        Customer::factory()->forBranch($b1)->count(2)->create();
        Customer::factory()->forBranch($b2)->count(3)->create();

        $this->actingAs($admin)
            ->get(route('customers.index'))
            ->assertOk();
    }

    public function test_super_admin_can_create_lead_using_own_branch_id_null(): void
    {
        // Super-admin có branch_id=null. Service set branch_id từ user, nên
        // payload tạo customer chỉ thực sự thành công khi super-admin có branch.
        $branch = Branch::factory()->create();
        $admin = $this->makeUser('super-admin', $branch);

        $payload = [
            'name' => 'Trường ABC',
            'status' => 'new',
        ];

        $this->actingAs($admin)
            ->post(route('customers.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'name' => 'Trường ABC',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_super_admin_without_branch_creates_lead_by_picking_branch(): void
    {
        // Kịch bản thật: super-admin toàn cục (branch_id=null) phải chọn
        // chi nhánh trong form. Service dùng branch_id đã validate từ request.
        $branch = Branch::factory()->create();
        $admin = $this->makeUser('super-admin'); // không gán branch → branch_id null

        $this->assertNull($admin->branch_id);

        $this->actingAs($admin)
            ->post(route('customers.store'), [
                'name' => 'Trường XYZ',
                'status' => 'new',
                'branch_id' => $branch->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'name' => 'Trường XYZ',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_super_admin_without_branch_must_supply_branch(): void
    {
        // Thiếu branch_id → validation lỗi, không tạo customer (chứ không phải 500).
        $admin = $this->makeUser('super-admin');

        $this->actingAs($admin)
            ->post(route('customers.store'), [
                'name' => 'Trường No Branch',
                'status' => 'new',
            ])
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseMissing('customers', [
            'name' => 'Trường No Branch',
        ]);
    }

    public function test_super_admin_can_assign_user_of_chosen_branch_on_create(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $admin = $this->makeUser('super-admin');

        $this->actingAs($admin)
            ->post(route('customers.store'), [
                'name' => 'Trường Có Phụ Trách',
                'status' => 'new',
                'branch_id' => $branch->id,
                'assigned_user_id' => $sales->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'name' => 'Trường Có Phụ Trách',
            'branch_id' => $branch->id,
            'assigned_user_id' => $sales->id,
        ]);
    }

    public function test_create_rejects_assignee_from_other_branch(): void
    {
        // Guard: assignee phải cùng chi nhánh với customer — chặn cả khi request
        // được "chế" tay với user thuộc chi nhánh khác.
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $otherSales = $this->makeUser('sales', $branchB);
        $admin = $this->makeUser('super-admin');

        $this->actingAs($admin)
            ->post(route('customers.store'), [
                'name' => 'Trường Sai Chi Nhánh',
                'status' => 'new',
                'branch_id' => $branchA->id,
                'assigned_user_id' => $otherSales->id,
            ])
            ->assertSessionHasErrors('assigned_user_id');

        $this->assertDatabaseMissing('customers', [
            'name' => 'Trường Sai Chi Nhánh',
        ]);
    }

    public function test_super_admin_can_view_any_lead(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->forBranch($branch)->create();

        $this->actingAs($admin)
            ->get(route('customers.show', $customer))
            ->assertOk();
    }

    public function test_super_admin_can_update_any_lead(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->forBranch($branch)->create([
            'name' => 'Old',
            'status' => 'new',
        ]);

        $this->actingAs($admin)
            ->put(route('customers.update', $customer), [
                'name' => 'New Name',
                'status' => 'contacted',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'New Name',
            'status' => 'contacted',
        ]);
    }

    public function test_super_admin_can_destroy_any_lead(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->forBranch($branch)->create();

        $this->actingAs($admin)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    // ───────────────────── branch-manager ─────────────────────

    public function test_branch_manager_can_view_lead_in_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $customer = Customer::factory()->forBranch($branch)->create();

        $this->actingAs($mgr)
            ->get(route('customers.show', $customer))
            ->assertOk();
    }

    public function test_branch_manager_cannot_view_lead_in_other_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $customer = Customer::factory()->forBranch($other)->create();

        // BranchScope ẩn customer thuộc branch khác → 404 model not found.
        $this->actingAs($mgr)
            ->get(route('customers.show', $customer))
            ->assertNotFound();
    }

    public function test_branch_manager_can_create_lead_for_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $this->actingAs($mgr)
            ->post(route('customers.store'), [
                'name' => 'Trường XYZ',
                'status' => 'new',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'name' => 'Trường XYZ',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_service_layer_ignores_user_supplied_branch_id(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);

        // Cố tình gửi branch_id của branch khác trong payload — phải bị bỏ qua.
        $this->actingAs($mgr)
            ->post(route('customers.store'), [
                'name' => 'Hack School',
                'status' => 'new',
                'branch_id' => $other->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'name' => 'Hack School',
            'branch_id' => $own->id,
        ]);
        $this->assertDatabaseMissing('customers', [
            'name' => 'Hack School',
            'branch_id' => $other->id,
        ]);
    }

    public function test_branch_manager_can_update_lead_in_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $customer = Customer::factory()->forBranch($branch)->create(['name' => 'Old']);

        $this->actingAs($mgr)
            ->put(route('customers.update', $customer), [
                'name' => 'Updated',
                'status' => 'qualified',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated',
        ]);
    }

    public function test_branch_manager_can_delete_lead_in_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $customer = Customer::factory()->forBranch($branch)->create();

        $this->actingAs($mgr)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    // ───────────────────── sales ─────────────────────

    public function test_sales_can_view_assigned_lead(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $customer = Customer::factory()->forBranch($branch)->create([
            'assigned_user_id' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->get(route('customers.show', $customer))
            ->assertOk();
    }

    public function test_sales_cannot_view_other_sales_lead(): void
    {
        $branch = Branch::factory()->create();
        $sales1 = $this->makeUser('sales', $branch);
        $sales2 = $this->makeUser('sales', $branch);
        $customer = Customer::factory()->forBranch($branch)->create([
            'assigned_user_id' => $sales2->id,
        ]);

        $this->actingAs($sales1)
            ->get(route('customers.show', $customer))
            ->assertForbidden();
    }

    public function test_sales_cannot_update_other_sales_lead(): void
    {
        $branch = Branch::factory()->create();
        $sales1 = $this->makeUser('sales', $branch);
        $sales2 = $this->makeUser('sales', $branch);
        $customer = Customer::factory()->forBranch($branch)->create([
            'assigned_user_id' => $sales2->id,
            'name' => 'Original',
        ]);

        $this->actingAs($sales1)
            ->put(route('customers.update', $customer), [
                'name' => 'Hacked',
                'status' => 'new',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Original',
        ]);
    }

    public function test_sales_cannot_delete_lead(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $customer = Customer::factory()->forBranch($branch)->create([
            'assigned_user_id' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->delete(route('customers.destroy', $customer))
            ->assertForbidden();

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    // ───────────────────── filter ─────────────────────

    public function test_filter_by_status(): void
    {
        $branch = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');

        Customer::factory()->forBranch($branch)->status('new')->count(2)->create();
        Customer::factory()->forBranch($branch)->status('won')->count(3)->create();

        $response = $this->actingAs($admin)
            ->get(route('customers.index', ['status' => 'won']))
            ->assertOk();

        $customers = $response->viewData('customers');
        $this->assertSame(3, $customers->total());
    }

    public function test_filter_by_assigned_user_id(): void
    {
        $branch = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $sales = $this->makeUser('sales', $branch);

        Customer::factory()->forBranch($branch)->count(2)->create(['assigned_user_id' => $sales->id]);
        Customer::factory()->forBranch($branch)->count(3)->create(['assigned_user_id' => null]);

        $response = $this->actingAs($admin)
            ->get(route('customers.index', ['assigned_user_id' => $sales->id]))
            ->assertOk();

        $customers = $response->viewData('customers');
        $this->assertSame(2, $customers->total());
    }

    // ───────────────────── assign ─────────────────────

    public function test_branch_manager_can_assign_sales_in_same_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $sales = $this->makeUser('sales', $branch);
        $customer = Customer::factory()->forBranch($branch)->create();

        $this->actingAs($mgr)
            ->post(route('customers.assign', $customer), ['assigned_user_id' => $sales->id])
            ->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'assigned_user_id' => $sales->id,
        ]);
    }

    public function test_branch_manager_cannot_assign_user_from_different_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $foreignSales = $this->makeUser('sales', $other);
        $customer = Customer::factory()->forBranch($own)->create();

        $this->actingAs($mgr)
            ->from(route('customers.show', $customer))
            ->post(route('customers.assign', $customer), ['assigned_user_id' => $foreignSales->id])
            ->assertSessionHasErrors('assigned_user_id');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'assigned_user_id' => null,
        ]);
    }

    public function test_sales_cannot_assign_lead(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $customer = Customer::factory()->forBranch($branch)->create([
            'assigned_user_id' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->post(route('customers.assign', $customer), ['assigned_user_id' => $sales->id])
            ->assertForbidden();
    }
}
