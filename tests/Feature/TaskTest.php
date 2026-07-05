<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Scopes\BranchScope;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRbac();
    }

    private function basePayload(array $override = []): array
    {
        return array_merge([
            'title' => 'Gọi điện tư vấn',
            'description' => 'Trao đổi về chương trình hợp tác',
            'type' => TaskType::Call->value,
            'priority' => TaskPriority::Medium->value,
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'reminder_enabled' => 0,
        ], $override);
    }

    // ───────────────────── guest ─────────────────────

    public function test_guest_redirected_from_index(): void
    {
        $this->get(route('tasks.index'))->assertRedirect(route('login'));
    }

    public function test_guest_redirected_from_store(): void
    {
        $this->post(route('tasks.store'), [])->assertRedirect(route('login'));
    }

    // ───────────────────── super-admin ─────────────────────

    public function test_super_admin_can_create_task_for_any_branch(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);

        $this->actingAs($admin)
            ->post(route('tasks.store'), $this->basePayload([
                'assigned_user_id' => $sales->id,
            ]))
            ->assertRedirect();

        $task = Task::withoutGlobalScope(BranchScope::class)
            ->where('title', 'Gọi điện tư vấn')->firstOrFail();

        $this->assertSame($sales->id, $task->assigned_user_id);
        $this->assertSame($branch->id, $task->branch_id);
        $this->assertSame($admin->id, $task->created_by);
        $this->assertSame(TaskStatus::Pending, $task->status);
    }

    public function test_super_admin_can_view_task_in_any_branch(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->create();

        $this->actingAs($admin)
            ->get(route('tasks.show', $task))
            ->assertOk();
    }

    // ───────────────────── branch-manager ─────────────────────

    public function test_branch_manager_can_create_task_in_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $sales = $this->makeUser('sales', $branch);

        $this->actingAs($mgr)
            ->post(route('tasks.store'), $this->basePayload([
                'assigned_user_id' => $sales->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Gọi điện tư vấn',
            'assigned_user_id' => $sales->id,
            'branch_id' => $branch->id,
            'created_by' => $mgr->id,
        ]);
    }

    public function test_branch_manager_cannot_assign_task_to_other_branch_user(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $foreignSales = $this->makeUser('sales', $other);

        $this->actingAs($mgr)
            ->from(route('tasks.create'))
            ->post(route('tasks.store'), $this->basePayload([
                'assigned_user_id' => $foreignSales->id,
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('assigned_user_id');

        $this->assertDatabaseMissing('tasks', ['title' => 'Gọi điện tư vấn']);
    }

    public function test_branch_manager_can_view_any_task_in_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $sales = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->create();

        $this->actingAs($mgr)
            ->get(route('tasks.show', $task))
            ->assertOk();
    }

    public function test_branch_manager_cannot_view_task_from_other_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $foreignSales = $this->makeUser('sales', $other);
        $task = Task::factory()->forUser($foreignSales)->create();

        // BranchScope ẩn task khác branch → 404.
        $this->actingAs($mgr)
            ->get(route('tasks.show', $task))
            ->assertNotFound();
    }

    public function test_branch_manager_can_delete_task(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $sales = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->create();

        $this->actingAs($mgr)
            ->delete(route('tasks.destroy', $task))
            ->assertRedirect(route('tasks.index'));

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    // ───────────────────── sales ─────────────────────

    public function test_sales_can_create_task_for_self(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);

        $this->actingAs($sales)
            ->post(route('tasks.store'), $this->basePayload([
                'assigned_user_id' => $sales->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'assigned_user_id' => $sales->id,
            'created_by' => $sales->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_sales_cannot_view_task_assigned_to_another_sales(): void
    {
        $branch = Branch::factory()->create();
        $salesA = $this->makeUser('sales', $branch);
        $salesB = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($salesB)->create();

        $this->actingAs($salesA)
            ->get(route('tasks.show', $task))
            ->assertForbidden();
    }

    public function test_sales_can_view_task_for_lead_they_own(): void
    {
        $branch = Branch::factory()->create();
        $salesOwner = $this->makeUser('sales', $branch);
        $salesAssignee = $this->makeUser('sales', $branch);

        $customer = Customer::factory()->forBranch($branch)->create([
            'assigned_user_id' => $salesOwner->id,
        ]);
        $task = Task::factory()->forUser($salesAssignee)->create([
            'customer_id' => $customer->id,
        ]);

        // Sales sở hữu Customer vẫn được xem task của Customer đó.
        $this->actingAs($salesOwner)
            ->get(route('tasks.show', $task))
            ->assertOk();
    }

    public function test_sales_can_complete_own_task(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->status(TaskStatus::Pending)->create();

        $this->actingAs($sales)
            ->post(route('tasks.complete', $task))
            ->assertRedirect();

        $task->refresh();
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertSame($sales->id, $task->completed_by);
    }

    // ───────────────────── validation ─────────────────────

    public function test_validation_title_required(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $this->actingAs($mgr)
            ->from(route('tasks.create'))
            ->post(route('tasks.store'), $this->basePayload([
                'title' => '',
                'assigned_user_id' => $mgr->id,
            ]))
            ->assertRedirect(route('tasks.create'))
            ->assertSessionHasErrors('title');
    }

    public function test_validation_title_min_length(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $this->actingAs($mgr)
            ->from(route('tasks.create'))
            ->post(route('tasks.store'), $this->basePayload([
                'title' => 'ab',
                'assigned_user_id' => $mgr->id,
            ]))
            ->assertSessionHasErrors('title');
    }

    public function test_validation_due_at_cannot_be_past_on_create(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $this->actingAs($mgr)
            ->from(route('tasks.create'))
            ->post(route('tasks.store'), $this->basePayload([
                'due_at' => now()->subDay()->format('Y-m-d H:i:s'),
                'assigned_user_id' => $mgr->id,
            ]))
            ->assertSessionHasErrors('due_at');
    }

    public function test_validation_priority_must_be_in_enum(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $this->actingAs($mgr)
            ->from(route('tasks.create'))
            ->post(route('tasks.store'), $this->basePayload([
                'priority' => 'super-mega-urgent',
                'assigned_user_id' => $mgr->id,
            ]))
            ->assertSessionHasErrors('priority');
    }

    public function test_validation_lead_must_be_same_branch_as_assignee(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $sales = $this->makeUser('sales', $branchA);
        $foreignLead = Customer::factory()->forBranch($branchB)->create();

        $this->actingAs($admin)
            ->from(route('tasks.create'))
            ->post(route('tasks.store'), $this->basePayload([
                'assigned_user_id' => $sales->id,
                'customer_id' => $foreignLead->id,
            ]))
            ->assertSessionHasErrors('customer_id');
    }

    // ───────────────────── service injection / cross-branch defense ─────────────────────

    public function test_service_layer_ignores_user_supplied_branch_id_and_created_by(): void
    {
        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $intruder = $this->makeUser('sales', $other);

        $this->actingAs($mgr)
            ->post(route('tasks.store'), $this->basePayload([
                'assigned_user_id' => $mgr->id,
                'branch_id' => $other->id,
                'created_by' => $intruder->id,
                'completed_at' => now()->toDateTimeString(),
                'completed_by' => $intruder->id,
            ]))
            ->assertRedirect();

        $task = Task::withoutGlobalScope(BranchScope::class)
            ->where('title', 'Gọi điện tư vấn')->firstOrFail();

        $this->assertSame($branch->id, $task->branch_id, 'branch_id phải lấy từ assignee, bỏ qua input');
        $this->assertSame($mgr->id, $task->created_by, 'created_by phải = auth user');
        $this->assertNull($task->completed_at, 'completed_at phải null khi tạo mới');
        $this->assertNull($task->completed_by, 'completed_by phải null khi tạo mới');
    }

    // ───────────────────── completion atomicity ─────────────────────

    public function test_completing_task_sets_status_completed_at_and_completed_by_atomically(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->status(TaskStatus::Pending)->create();

        $this->actingAs($sales)
            ->post(route('tasks.complete', $task))
            ->assertRedirect();

        $task->refresh();

        // Atomic: cả ba trường phải được set đồng thời.
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertSame($sales->id, $task->completed_by);
    }

    public function test_reopening_task_clears_completion_metadata(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->status(TaskStatus::Completed)->create([
            'completed_at' => now(),
            'completed_by' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->post(route('tasks.reopen', $task))
            ->assertRedirect();

        $task->refresh();
        $this->assertSame(TaskStatus::Pending, $task->status);
        $this->assertNull($task->completed_at);
        $this->assertNull($task->completed_by);
    }

    public function test_cannot_complete_cancelled_task(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $task = Task::factory()->forUser($mgr)->status(TaskStatus::Cancelled)->create();

        $this->actingAs($mgr)
            ->post(route('tasks.complete', $task))
            ->assertSessionHasErrors('status');

        $task->refresh();
        $this->assertSame(TaskStatus::Cancelled, $task->status);
    }

    // ───────────────────── BranchScope isolation ─────────────────────

    public function test_index_does_not_leak_tasks_from_other_branch(): void
    {
        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();

        $mgr = $this->makeUser('branch-manager', $branch);
        $foreignMgr = $this->makeUser('branch-manager', $other);

        $own = Task::factory()->forUser($mgr)->create(['title' => 'Own task XYZ']);
        $foreign = Task::factory()->forUser($foreignMgr)->create(['title' => 'Foreign task XYZ']);

        $response = $this->actingAs($mgr)
            ->get(route('tasks.index'))
            ->assertOk();

        $this->assertStringContainsString('Own task XYZ', $response->getContent());
        $this->assertStringNotContainsString('Foreign task XYZ', $response->getContent());
    }

    // ───────────────────── overdue ─────────────────────

    public function test_is_overdue_attribute_reflects_due_at_and_status(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $overdue = Task::factory()->forUser($mgr)->status(TaskStatus::Pending)
            ->create(['due_at' => now()->subDay()]);
        $future = Task::factory()->forUser($mgr)->status(TaskStatus::Pending)
            ->create(['due_at' => now()->addDay()]);
        $completedPast = Task::factory()->forUser($mgr)->status(TaskStatus::Completed)
            ->create(['due_at' => now()->subDay(), 'completed_at' => now(), 'completed_by' => $mgr->id]);

        $this->assertTrue($overdue->is_overdue);
        $this->assertFalse($future->is_overdue);
        $this->assertFalse($completedPast->is_overdue);
    }

    // ───────────────────── customer cascade ─────────────────────

    public function test_deleting_lead_cascades_to_its_tasks(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $customer = Customer::factory()->forBranch($branch)->create();

        Task::factory()->forUser($sales)->count(3)->create(['customer_id' => $customer->id, 'branch_id' => $branch->id]);

        $this->assertSame(3, Task::withoutGlobalScope(BranchScope::class)
            ->where('customer_id', $customer->id)->count());

        $customer->delete();

        $this->assertSame(0, Task::withoutGlobalScope(BranchScope::class)
            ->where('customer_id', $customer->id)->count());
    }

    // ───────────────────── filter ─────────────────────

    public function test_index_filter_by_due_overdue_only_shows_overdue_tasks(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        Task::factory()->forUser($mgr)->status(TaskStatus::Pending)->create([
            'title' => 'OVERDUE_ONE',
            'due_at' => now()->subDays(2),
        ]);
        Task::factory()->forUser($mgr)->status(TaskStatus::Pending)->create([
            'title' => 'FUTURE_ONE',
            'due_at' => now()->addDays(2),
        ]);

        $response = $this->actingAs($mgr)
            ->get(route('tasks.index', ['due' => 'overdue']))
            ->assertOk();

        $this->assertStringContainsString('OVERDUE_ONE', $response->getContent());
        $this->assertStringNotContainsString('FUTURE_ONE', $response->getContent());
    }
}
