<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class LeadTest extends TestCase
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
        $this->get(route('leads.index'))
            ->assertRedirect(route('login'));
    }

    // ───────────────────── super-admin ─────────────────────

    public function test_super_admin_can_view_index_across_branches(): void
    {
        $admin = $this->makeUser('super-admin');
        $b1 = Branch::factory()->create();
        $b2 = Branch::factory()->create();
        Lead::factory()->forBranch($b1)->count(2)->create();
        Lead::factory()->forBranch($b2)->count(3)->create();

        $this->actingAs($admin)
            ->get(route('leads.index'))
            ->assertOk();
    }

    public function test_super_admin_can_create_lead_using_own_branch_id_null(): void
    {
        // Super-admin có branch_id=null. Service set branch_id từ user, nên
        // payload tạo lead chỉ thực sự thành công khi super-admin có branch.
        $branch = Branch::factory()->create();
        $admin = $this->makeUser('super-admin', $branch);

        $payload = [
            'school_name' => 'Trường ABC',
            'school_level' => 'thcs',
            'student_size' => 500,
            'status' => 'new',
        ];

        $this->actingAs($admin)
            ->post(route('leads.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'school_name' => 'Trường ABC',
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
            ->post(route('leads.store'), [
                'school_name' => 'Trường XYZ',
                'school_level' => 'thcs',
                'student_size' => 12,
                'status' => 'new',
                'branch_id' => $branch->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'school_name' => 'Trường XYZ',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_super_admin_without_branch_must_supply_branch(): void
    {
        // Thiếu branch_id → validation lỗi, không tạo lead (chứ không phải 500).
        $admin = $this->makeUser('super-admin');

        $this->actingAs($admin)
            ->post(route('leads.store'), [
                'school_name' => 'Trường No Branch',
                'school_level' => 'thcs',
                'status' => 'new',
            ])
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseMissing('leads', [
            'school_name' => 'Trường No Branch',
        ]);
    }

    public function test_super_admin_can_view_any_lead(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $lead = Lead::factory()->forBranch($branch)->create();

        $this->actingAs($admin)
            ->get(route('leads.show', $lead))
            ->assertOk();
    }

    public function test_super_admin_can_update_any_lead(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $lead = Lead::factory()->forBranch($branch)->create([
            'school_name' => 'Old',
            'status' => 'new',
        ]);

        $this->actingAs($admin)
            ->put(route('leads.update', $lead), [
                'school_name' => 'New Name',
                'school_level' => 'thpt',
                'student_size' => 600,
                'status' => 'contacted',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'school_name' => 'New Name',
            'status' => 'contacted',
        ]);
    }

    public function test_super_admin_can_destroy_any_lead(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $lead = Lead::factory()->forBranch($branch)->create();

        $this->actingAs($admin)
            ->delete(route('leads.destroy', $lead))
            ->assertRedirect(route('leads.index'));

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }

    // ───────────────────── branch-manager ─────────────────────

    public function test_branch_manager_can_view_lead_in_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $lead = Lead::factory()->forBranch($branch)->create();

        $this->actingAs($mgr)
            ->get(route('leads.show', $lead))
            ->assertOk();
    }

    public function test_branch_manager_cannot_view_lead_in_other_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $lead = Lead::factory()->forBranch($other)->create();

        // BranchScope ẩn lead thuộc branch khác → 404 model not found.
        $this->actingAs($mgr)
            ->get(route('leads.show', $lead))
            ->assertNotFound();
    }

    public function test_branch_manager_can_create_lead_for_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $this->actingAs($mgr)
            ->post(route('leads.store'), [
                'school_name' => 'Trường XYZ',
                'school_level' => 'tieu_hoc',
                'student_size' => 300,
                'status' => 'new',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'school_name' => 'Trường XYZ',
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
            ->post(route('leads.store'), [
                'school_name' => 'Hack School',
                'school_level' => 'thcs',
                'student_size' => 100,
                'status' => 'new',
                'branch_id' => $other->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'school_name' => 'Hack School',
            'branch_id' => $own->id,
        ]);
        $this->assertDatabaseMissing('leads', [
            'school_name' => 'Hack School',
            'branch_id' => $other->id,
        ]);
    }

    public function test_branch_manager_can_update_lead_in_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $lead = Lead::factory()->forBranch($branch)->create(['school_name' => 'Old']);

        $this->actingAs($mgr)
            ->put(route('leads.update', $lead), [
                'school_name' => 'Updated',
                'school_level' => 'thpt',
                'student_size' => 800,
                'status' => 'qualified',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'school_name' => 'Updated',
        ]);
    }

    public function test_branch_manager_can_delete_lead_in_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $lead = Lead::factory()->forBranch($branch)->create();

        $this->actingAs($mgr)
            ->delete(route('leads.destroy', $lead))
            ->assertRedirect(route('leads.index'));

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }

    // ───────────────────── sales ─────────────────────

    public function test_sales_can_view_assigned_lead(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $lead = Lead::factory()->forBranch($branch)->create([
            'assigned_user_id' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->get(route('leads.show', $lead))
            ->assertOk();
    }

    public function test_sales_cannot_view_other_sales_lead(): void
    {
        $branch = Branch::factory()->create();
        $sales1 = $this->makeUser('sales', $branch);
        $sales2 = $this->makeUser('sales', $branch);
        $lead = Lead::factory()->forBranch($branch)->create([
            'assigned_user_id' => $sales2->id,
        ]);

        $this->actingAs($sales1)
            ->get(route('leads.show', $lead))
            ->assertForbidden();
    }

    public function test_sales_cannot_update_other_sales_lead(): void
    {
        $branch = Branch::factory()->create();
        $sales1 = $this->makeUser('sales', $branch);
        $sales2 = $this->makeUser('sales', $branch);
        $lead = Lead::factory()->forBranch($branch)->create([
            'assigned_user_id' => $sales2->id,
            'school_name' => 'Original',
        ]);

        $this->actingAs($sales1)
            ->put(route('leads.update', $lead), [
                'school_name' => 'Hacked',
                'school_level' => 'thcs',
                'student_size' => 100,
                'status' => 'new',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'school_name' => 'Original',
        ]);
    }

    public function test_sales_cannot_delete_lead(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $lead = Lead::factory()->forBranch($branch)->create([
            'assigned_user_id' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->delete(route('leads.destroy', $lead))
            ->assertForbidden();

        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }

    // ───────────────────── filter ─────────────────────

    public function test_filter_by_status(): void
    {
        $branch = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');

        Lead::factory()->forBranch($branch)->status('new')->count(2)->create();
        Lead::factory()->forBranch($branch)->status('won')->count(3)->create();

        $response = $this->actingAs($admin)
            ->get(route('leads.index', ['status' => 'won']))
            ->assertOk();

        $leads = $response->viewData('leads');
        $this->assertSame(3, $leads->total());
    }

    public function test_filter_by_school_level(): void
    {
        $branch = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');

        Lead::factory()->forBranch($branch)->count(2)->create(['school_level' => 'thpt']);
        Lead::factory()->forBranch($branch)->count(4)->create(['school_level' => 'mam_non']);

        $response = $this->actingAs($admin)
            ->get(route('leads.index', ['school_level' => 'mam_non']))
            ->assertOk();

        $leads = $response->viewData('leads');
        $this->assertSame(4, $leads->total());
    }

    public function test_filter_by_assigned_user_id(): void
    {
        $branch = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $sales = $this->makeUser('sales', $branch);

        Lead::factory()->forBranch($branch)->count(2)->create(['assigned_user_id' => $sales->id]);
        Lead::factory()->forBranch($branch)->count(3)->create(['assigned_user_id' => null]);

        $response = $this->actingAs($admin)
            ->get(route('leads.index', ['assigned_user_id' => $sales->id]))
            ->assertOk();

        $leads = $response->viewData('leads');
        $this->assertSame(2, $leads->total());
    }

    // ───────────────────── assign ─────────────────────

    public function test_branch_manager_can_assign_sales_in_same_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $sales = $this->makeUser('sales', $branch);
        $lead = Lead::factory()->forBranch($branch)->create();

        $this->actingAs($mgr)
            ->post(route('leads.assign', $lead), ['assigned_user_id' => $sales->id])
            ->assertRedirect(route('leads.show', $lead));

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'assigned_user_id' => $sales->id,
        ]);
    }

    public function test_branch_manager_cannot_assign_user_from_different_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $foreignSales = $this->makeUser('sales', $other);
        $lead = Lead::factory()->forBranch($own)->create();

        $this->actingAs($mgr)
            ->from(route('leads.show', $lead))
            ->post(route('leads.assign', $lead), ['assigned_user_id' => $foreignSales->id])
            ->assertSessionHasErrors('assigned_user_id');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'assigned_user_id' => null,
        ]);
    }

    public function test_sales_cannot_assign_lead(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $lead = Lead::factory()->forBranch($branch)->create([
            'assigned_user_id' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->post(route('leads.assign', $lead), ['assigned_user_id' => $sales->id])
            ->assertForbidden();
    }
}
