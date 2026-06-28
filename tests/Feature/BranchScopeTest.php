<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class BranchScopeTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRbac();
    }

    public function test_branch_manager_only_sees_leads_in_own_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        Lead::factory()->forBranch($branchA)->count(3)->create();
        Lead::factory()->forBranch($branchB)->count(3)->create();

        $manager = $this->makeUser('branch-manager', $branchA);
        Auth::login($manager);

        $this->assertSame(3, Lead::count());
        $this->assertTrue(
            Lead::all()->every(fn (Lead $l) => $l->branch_id === $branchA->id),
            'Tất cả lead nhìn thấy phải thuộc branch của manager.'
        );
    }

    public function test_super_admin_sees_all_leads_across_branches(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        Lead::factory()->forBranch($branchA)->count(3)->create();
        Lead::factory()->forBranch($branchB)->count(3)->create();

        $admin = $this->makeUser('super-admin');
        Auth::login($admin);

        $this->assertSame(6, Lead::count());
    }

    public function test_user_with_null_branch_id_and_no_super_admin_sees_zero_leads(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        Lead::factory()->forBranch($branchA)->count(3)->create();
        Lead::factory()->forBranch($branchB)->count(3)->create();

        // Sales role without branch_id (data anomaly): scope returns empty.
        $orphan = $this->makeUser('sales', null);

        Auth::login($orphan);

        $this->assertSame(0, Lead::count());
    }

    public function test_sales_only_sees_leads_in_own_branch_via_scope(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        Lead::factory()->forBranch($branchA)->count(3)->create();
        Lead::factory()->forBranch($branchB)->count(3)->create();

        $sales = $this->makeUser('sales', $branchA);
        Auth::login($sales);

        // Scope chỉ filter theo branch — chưa áp dụng assigned_user_id (đó là policy).
        $this->assertSame(3, Lead::count());
    }
}
