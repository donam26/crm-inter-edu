<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Branch;
use App\Models\Label;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class TaskWorkItemTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRbac();
    }

    // ───────────────────── comments ─────────────────────

    public function test_assignee_can_add_comment(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->create();

        $this->actingAs($sales)
            ->post(route('tasks.comments.store', $task), ['body' => 'Đã gọi, hẹn gặp thứ 5'])
            ->assertRedirect();

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $sales->id,
            'body' => 'Đã gọi, hẹn gặp thứ 5',
        ]);
    }

    public function test_sales_cannot_comment_on_task_outside_scope(): void
    {
        $branch = Branch::factory()->create();
        $bystander = $this->makeUser('sales', $branch);
        $owner = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($owner)->create();

        // Cùng chi nhánh nhưng KHÔNG phải assignee/creator/phụ trách lead và
        // không có view-all → không mở được task thì cũng không bình luận được.
        $this->actingAs($bystander)
            ->post(route('tasks.comments.store', $task), ['body' => 'ngoài phạm vi'])
            ->assertForbidden();

        $this->assertDatabaseMissing('task_comments', ['task_id' => $task->id]);
    }

    public function test_sales_cannot_delete_others_comment(): void
    {
        $branch = Branch::factory()->create();
        $owner = $this->makeUser('sales', $branch);
        $other = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($other)->create();
        $comment = TaskComment::create([
            'task_id' => $task->id, 'user_id' => $other->id, 'body' => 'ghi chú',
        ]);

        $this->actingAs($owner)
            ->delete(route('comments.destroy', $comment))
            ->assertForbidden();

        $this->assertNotSoftDeleted('task_comments', ['id' => $comment->id]);
    }

    public function test_manager_can_delete_others_comment_via_view_all(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $sales = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->create();
        $comment = TaskComment::create([
            'task_id' => $task->id, 'user_id' => $sales->id, 'body' => 'x',
        ]);

        $this->actingAs($mgr)
            ->delete(route('comments.destroy', $comment))
            ->assertRedirect();

        $this->assertSoftDeleted('task_comments', ['id' => $comment->id]);
    }

    // ───────────────────── checklist ─────────────────────

    public function test_add_and_toggle_checklist_item(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->create();

        $this->actingAs($sales)
            ->post(route('tasks.checklist.store', $task), ['title' => 'Chuẩn bị hồ sơ'])
            ->assertRedirect();

        $item = TaskChecklistItem::where('task_id', $task->id)->firstOrFail();
        $this->assertFalse($item->is_done);

        $this->actingAs($sales)
            ->patch(route('checklist.update', $item))
            ->assertRedirect();

        $this->assertTrue($item->fresh()->is_done);
    }

    public function test_foreign_sales_cannot_add_checklist(): void
    {
        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $foreign = $this->makeUser('sales', $other);
        $task = Task::factory()->forUser($sales)->create();

        // BranchScope ẩn task khỏi user branch khác → route-model-binding 404
        // (không lộ sự tồn tại), xảy ra trước cả policy.
        $this->actingAs($foreign)
            ->post(route('tasks.checklist.store', $task), ['title' => 'x'])
            ->assertNotFound();

        $this->assertDatabaseMissing('task_checklist_items', ['task_id' => $task->id]);
    }

    // ───────────────────── labels ─────────────────────

    public function test_manager_can_create_label_but_sales_cannot(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $sales = $this->makeUser('sales', $branch);

        $this->actingAs($mgr)
            ->post(route('labels.store'), ['name' => 'Ưu tiên cao', 'color' => 'danger'])
            ->assertRedirect();

        $this->assertDatabaseHas('labels', [
            'name' => 'Ưu tiên cao', 'branch_id' => $branch->id, 'color' => 'danger',
        ]);

        $this->actingAs($sales)
            ->post(route('labels.store'), ['name' => 'Khác', 'color' => 'primary'])
            ->assertForbidden();
    }

    public function test_sync_only_accepts_same_branch_labels(): void
    {
        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $task = Task::factory()->forUser($mgr)->create();

        $own = Label::create(['branch_id' => $branch->id, 'name' => 'A', 'color' => 'primary']);
        $foreign = Label::create(['branch_id' => $other->id, 'name' => 'B', 'color' => 'primary']);

        $this->actingAs($mgr)
            ->post(route('tasks.labels.sync', $task), ['label_ids' => [$own->id, $foreign->id]])
            ->assertRedirect();

        $this->assertDatabaseHas('label_task', ['task_id' => $task->id, 'label_id' => $own->id]);
        $this->assertDatabaseMissing('label_task', ['task_id' => $task->id, 'label_id' => $foreign->id]);
    }

    // ───────────────────── activity log ─────────────────────

    public function test_status_change_is_logged(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->status(TaskStatus::Pending)->create();

        $this->actingAs($sales)
            ->post(route('tasks.start', $task))
            ->assertRedirect();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $task->getMorphClass(),
            'subject_id' => $task->id,
            'event' => 'updated',
        ]);
    }

    // ───────────────────── show page render ─────────────────────

    public function test_show_page_renders_work_items(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->create();
        TaskChecklistItem::create(['task_id' => $task->id, 'title' => 'Bước 1', 'position' => 1]);
        TaskComment::create(['task_id' => $task->id, 'user_id' => $sales->id, 'body' => 'ghi chú test']);

        $this->actingAs($sales)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Checklist')
            ->assertSee('ghi chú test');
    }
}
