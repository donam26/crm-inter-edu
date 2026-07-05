<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Branch;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRbac();
    }

    // ───────────────────── guest ─────────────────────

    public function test_guest_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    // ───────────────────── super-admin ─────────────────────

    public function test_super_admin_sees_total_counts_across_branches(): void
    {
        $admin = $this->makeUser('super-admin');
        $b1 = Branch::factory()->create();
        $b2 = Branch::factory()->create();

        Customer::factory()->forBranch($b1)->count(2)->create();
        Customer::factory()->forBranch($b2)->count(3)->create();

        $response = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();

        $stats = $response->viewData('stats');

        $this->assertSame(5, $stats['total_customers']);
        $this->assertArrayHasKey('customers_by_branch', $stats);
        $this->assertSame(2, $stats['customers_by_branch'][$b1->id]);
        $this->assertSame(3, $stats['customers_by_branch'][$b2->id]);
    }

    // ───────────────────── branch-manager ─────────────────────

    public function test_branch_manager_sees_own_branch_only(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branchA);

        Customer::factory()->forBranch($branchA)->count(3)->create();
        Customer::factory()->forBranch($branchB)->count(7)->create();

        $response = $this->actingAs($mgr)
            ->get(route('dashboard'))
            ->assertOk();

        $stats = $response->viewData('stats');

        $this->assertSame(3, $stats['total_customers']);
        $this->assertArrayNotHasKey('customers_by_branch', $stats);
    }

    // ───────────────────── sales ─────────────────────

    public function test_sales_sees_only_assigned_leads(): void
    {
        $branch = Branch::factory()->create();
        $sales1 = $this->makeUser('sales', $branch);
        $sales2 = $this->makeUser('sales', $branch);

        Customer::factory()->forBranch($branch)->count(2)->create([
            'assigned_user_id' => $sales1->id,
        ]);
        Customer::factory()->forBranch($branch)->count(5)->create([
            'assigned_user_id' => $sales2->id,
        ]);
        Customer::factory()->forBranch($branch)->count(3)->create([
            'assigned_user_id' => null,
        ]);

        $response = $this->actingAs($sales1)
            ->get(route('dashboard'))
            ->assertOk();

        $stats = $response->viewData('stats');

        $this->assertSame(2, $stats['total_customers']);
        $this->assertArrayNotHasKey('customers_by_branch', $stats);
    }

    // ───────────────────── reflect new customer ─────────────────────

    public function test_dashboard_reflects_new_lead(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $response = $this->actingAs($mgr)
            ->get(route('dashboard'))
            ->assertOk();
        $this->assertSame(0, $response->viewData('stats')['total_customers']);

        Customer::factory()->forBranch($branch)->create();

        $response = $this->actingAs($mgr)
            ->get(route('dashboard'))
            ->assertOk();
        $this->assertSame(1, $response->viewData('stats')['total_customers']);
    }

    // ───────────────────── activities last 7 days ─────────────────────

    public function test_activities_last_7_days_only_recent(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $customer = Customer::factory()->forBranch($branch)->create();

        Activity::factory()->forLead($customer)->create([
            'user_id' => $mgr->id,
            'happened_at' => now()->subDays(2),
        ]);
        Activity::factory()->forLead($customer)->create([
            'user_id' => $mgr->id,
            'happened_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($mgr)
            ->get(route('dashboard'))
            ->assertOk();

        $stats = $response->viewData('stats');
        $this->assertSame(1, $stats['activities_last_7_days']);
    }

    // ───────────────────── total contacts scoped ─────────────────────

    public function test_total_contacts_scoped_per_role(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $mgr = $this->makeUser('branch-manager', $branchA);

        $leadA = Customer::factory()->forBranch($branchA)->create();
        $leadB = Customer::factory()->forBranch($branchB)->create();

        Contact::factory()->forLead($leadA)->count(3)->create();
        Contact::factory()->forLead($leadB)->count(5)->create();

        $adminResponse = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();
        $this->assertSame(8, $adminResponse->viewData('stats')['total_contacts']);

        $mgrResponse = $this->actingAs($mgr)
            ->get(route('dashboard'))
            ->assertOk();
        $this->assertSame(3, $mgrResponse->viewData('stats')['total_contacts']);
    }
}
