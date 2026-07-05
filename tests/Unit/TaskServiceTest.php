<?php

namespace Tests\Unit;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Scopes\BranchScope;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * Unit tests for TaskService.
 *
 * Phạm vi: kiểm chứng business invariants ở Service layer:
 *  - branch_id luôn lấy từ assignee (không từ input)
 *  - created_by = auth user
 *  - cross-branch guards (assignee, customer)
 *  - completion atomicity (status + completed_at + completed_by đồng bộ)
 *  - reopen clears completion metadata
 *  - rollback transaction khi exception
 */
class TaskServiceTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    private TaskService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TaskService;
        $this->setUpRbac();
    }

    private function basePayload(int $assigneeId, array $extra = []): array
    {
        return array_merge([
            'title' => 'Sample task',
            'description' => null,
            'type' => TaskType::Call->value,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::Pending->value,
            'due_at' => now()->addDay(),
            'assigned_user_id' => $assigneeId,
            'customer_id' => null,
            'reminder_enabled' => false,
            'remind_at' => null,
        ], $extra);
    }

    // ───────────────────── create ─────────────────────

    public function test_create_sets_branch_id_from_assignee_not_from_input(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $assignee = $this->makeUser('sales', $branchA);

        Auth::login($admin);

        $task = $this->service->create($this->basePayload($assignee->id, [
            // Cố tình gửi branch_id sai → phải bị bỏ qua.
            'branch_id' => $branchB->id,
        ]));

        $this->assertSame($branchA->id, $task->branch_id);
        $this->assertSame($assignee->id, $task->assigned_user_id);
    }

    public function test_create_sets_created_by_from_auth_user(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $sales = $this->makeUser('sales', $branch);
        $intruder = $this->makeUser('sales', $branch);

        Auth::login($mgr);

        $task = $this->service->create($this->basePayload($sales->id, [
            'created_by' => $intruder->id,
        ]));

        $this->assertSame($mgr->id, $task->created_by);
    }

    public function test_create_initializes_completion_fields_as_null(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        Auth::login($mgr);

        $task = $this->service->create($this->basePayload($mgr->id, [
            'completed_at' => now()->toDateTimeString(),
            'completed_by' => $mgr->id,
        ]));

        $this->assertNull($task->completed_at);
        $this->assertNull($task->completed_by);
    }

    public function test_create_rejects_cross_branch_assignee_for_non_super_admin(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $foreign = $this->makeUser('sales', $other);

        Auth::login($mgr);

        $this->expectException(ValidationException::class);
        $this->service->create($this->basePayload($foreign->id));
    }

    public function test_create_allows_super_admin_to_assign_to_any_branch(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);

        Auth::login($admin);

        $task = $this->service->create($this->basePayload($sales->id));

        $this->assertSame($branch->id, $task->branch_id);
        $this->assertSame($admin->id, $task->created_by);
    }

    public function test_create_rejects_lead_from_other_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $sales = $this->makeUser('sales', $branchA);
        $foreignLead = Customer::factory()->forBranch($branchB)->create();

        Auth::login($admin);

        $this->expectException(ValidationException::class);
        $this->service->create($this->basePayload($sales->id, [
            'customer_id' => $foreignLead->id,
        ]));
    }

    public function test_create_normalizes_invalid_status_to_pending(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        Auth::login($mgr);

        $task = $this->service->create($this->basePayload($mgr->id, [
            // Service::create chỉ chấp nhận pending/in_progress khi tạo;
            // ngoài ra phải normalize về Pending.
            'status' => TaskStatus::Completed->value,
        ]));

        $this->assertSame(TaskStatus::Pending, $task->status);
    }

    // ───────────────────── update ─────────────────────

    public function test_update_keeps_branch_id_in_sync_with_assignee_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $userA = $this->makeUser('sales', $branchA);
        $userB = $this->makeUser('sales', $branchB);

        Auth::login($admin);

        $task = $this->service->create($this->basePayload($userA->id));
        $this->assertSame($branchA->id, $task->branch_id);

        // Đổi assignee sang user thuộc branch khác → branch_id phải đồng bộ.
        $updated = $this->service->update($task, [
            'title' => $task->title,
            'description' => $task->description,
            'type' => $task->type->value,
            'priority' => $task->priority->value,
            'status' => $task->status->value,
            'due_at' => $task->due_at,
            'assigned_user_id' => $userB->id,
        ]);

        $this->assertSame($branchB->id, $updated->branch_id);
        $this->assertSame($userB->id, $updated->assigned_user_id);
    }

    public function test_update_marks_completion_metadata_when_status_set_to_completed(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        Auth::login($mgr);

        $task = $this->service->create($this->basePayload($mgr->id));

        $updated = $this->service->update($task, [
            'title' => $task->title,
            'description' => $task->description,
            'type' => $task->type->value,
            'priority' => $task->priority->value,
            'status' => TaskStatus::Completed->value,
            'due_at' => $task->due_at,
            'assigned_user_id' => $mgr->id,
        ]);

        $this->assertSame(TaskStatus::Completed, $updated->status);
        $this->assertNotNull($updated->completed_at);
        $this->assertSame($mgr->id, $updated->completed_by);
    }

    public function test_update_clears_completion_metadata_when_reopening(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        Auth::login($mgr);

        $task = Task::factory()->forUser($mgr)->status(TaskStatus::Completed)->create([
            'completed_at' => now(),
            'completed_by' => $mgr->id,
        ]);

        $updated = $this->service->update($task, [
            'title' => $task->title,
            'description' => $task->description,
            'type' => $task->type->value,
            'priority' => $task->priority->value,
            'status' => TaskStatus::Pending->value,
            'due_at' => $task->due_at,
            'assigned_user_id' => $mgr->id,
        ]);

        $this->assertSame(TaskStatus::Pending, $updated->status);
        $this->assertNull($updated->completed_at);
        $this->assertNull($updated->completed_by);
    }

    // ───────────────────── complete / reopen ─────────────────────

    public function test_complete_is_atomic(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);

        Auth::login($sales);

        $task = $this->service->create($this->basePayload($sales->id));
        $this->assertNull($task->completed_at);

        $completed = $this->service->complete($task);

        // Postcondition: status + completed_at + completed_by đồng bộ.
        $this->assertSame(TaskStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame($sales->id, $completed->completed_by);
    }

    public function test_complete_throws_for_cancelled_task(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        Auth::login($mgr);

        $task = Task::factory()->forUser($mgr)->status(TaskStatus::Cancelled)->create();

        $this->expectException(ValidationException::class);
        $this->service->complete($task);
    }

    public function test_reopen_clears_completion_fields(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        Auth::login($mgr);

        $task = Task::factory()->forUser($mgr)->status(TaskStatus::Completed)->create([
            'completed_at' => now(),
            'completed_by' => $mgr->id,
        ]);

        $reopened = $this->service->reopen($task);

        $this->assertSame(TaskStatus::Pending, $reopened->status);
        $this->assertNull($reopened->completed_at);
        $this->assertNull($reopened->completed_by);
    }

    // ───────────────────── delete ─────────────────────

    public function test_delete_removes_task(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        Auth::login($mgr);
        $task = $this->service->create($this->basePayload($mgr->id));

        $this->service->delete($task);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    // ───────────────────── transaction safety ─────────────────────

    public function test_create_rolls_back_when_validation_fails_after_partial_setup(): void
    {
        // Validate qua guardLeadBranch xảy ra trong transaction sau khi
        // resolve assignee. Khi exception ném ra, transaction phải rollback
        // gọn (level về 0) và không có Task nào được tạo dở.
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $sales = $this->makeUser('sales', $branchA);
        $foreignLead = Customer::factory()->forBranch($branchB)->create();

        Auth::login($admin);

        $beforeLevel = DB::transactionLevel();
        $caught = null;

        try {
            $this->service->create($this->basePayload($sales->id, [
                'customer_id' => $foreignLead->id,
            ]));
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(ValidationException::class, $caught);
        $this->assertSame($beforeLevel, DB::transactionLevel());
        $this->assertSame(0, Task::withoutGlobalScope(BranchScope::class)->count());
    }
}
