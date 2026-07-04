<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Branch;
use App\Models\Task;
use App\Notifications\TaskNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class TaskNotificationTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRbac();
    }

    /** @param array<string, mixed> $override */
    private function payload(array $override = []): array
    {
        return array_merge([
            'title' => 'Gọi khách hàng',
            'type' => 'call',
            'priority' => 'medium',
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'reminder_enabled' => 0,
        ], $override);
    }

    // ───────────────────── assignment ─────────────────────

    public function test_assigning_task_notifies_assignee(): void
    {
        Notification::fake();
        $branch = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $sales = $this->makeUser('sales', $branch);

        $this->actingAs($admin)
            ->post(route('tasks.store'), $this->payload(['assigned_user_id' => $sales->id]))
            ->assertRedirect();

        Notification::assertSentTo($sales, TaskNotification::class, fn ($n) => $n->type === 'assigned');
        $this->assertDatabaseHas('task_watchers', ['user_id' => $sales->id]); // assignee auto-watch
    }

    public function test_reassign_detaches_previous_assignee_from_watchers(): void
    {
        Notification::fake();
        $branch = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $a = $this->makeUser('sales', $branch);
        $b = $this->makeUser('sales', $branch);

        $this->actingAs($admin)
            ->post(route('tasks.store'), $this->payload(['assigned_user_id' => $a->id]))
            ->assertRedirect();

        $task = Task::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertDatabaseHas('task_watchers', ['task_id' => $task->id, 'user_id' => $a->id]);

        // Giao lại cho B → A bị gỡ khỏi watcher, B được thêm.
        $this->actingAs($admin)
            ->put(route('tasks.update', $task), $this->payload([
                'assigned_user_id' => $b->id,
                'status' => 'pending',
            ]))
            ->assertRedirect();

        $this->assertDatabaseMissing('task_watchers', ['task_id' => $task->id, 'user_id' => $a->id]);
        $this->assertDatabaseHas('task_watchers', ['task_id' => $task->id, 'user_id' => $b->id]);
    }

    public function test_self_assigned_task_notifies_nobody(): void
    {
        Notification::fake();
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $this->actingAs($mgr)
            ->post(route('tasks.store'), $this->payload(['assigned_user_id' => $mgr->id]))
            ->assertRedirect();

        Notification::assertNothingSent();
    }

    // ───────────────────── comment ─────────────────────

    public function test_comment_notifies_watchers_except_commenter(): void
    {
        Notification::fake();
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $sales = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->create();
        $task->watchers()->attach([$mgr->id, $sales->id]);

        $this->actingAs($mgr)
            ->post(route('tasks.comments.store', $task), ['body' => 'cập nhật tiến độ'])
            ->assertRedirect();

        Notification::assertSentTo($sales, TaskNotification::class, fn ($n) => $n->type === 'commented');
        Notification::assertNotSentTo($mgr, TaskNotification::class);
    }

    // ───────────────────── watch / unwatch ─────────────────────

    public function test_user_can_watch_and_unwatch(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->create();

        $this->actingAs($sales)->post(route('tasks.watch', $task))->assertRedirect();
        $this->assertDatabaseHas('task_watchers', ['task_id' => $task->id, 'user_id' => $sales->id]);

        $this->actingAs($sales)->post(route('tasks.unwatch', $task))->assertRedirect();
        $this->assertDatabaseMissing('task_watchers', ['task_id' => $task->id, 'user_id' => $sales->id]);
    }

    // ───────────────────── scheduler / SLA ─────────────────────

    public function test_reminder_command_notifies_once(): void
    {
        Notification::fake();
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->status(TaskStatus::Pending)->create([
            'reminder_enabled' => true,
            'remind_at' => now()->subMinutes(5),
            'reminded_at' => null,
        ]);

        $this->artisan('tasks:dispatch-reminders')->assertSuccessful();

        Notification::assertSentTo($sales, TaskNotification::class, fn ($n) => $n->type === 'reminder');
        $this->assertNotNull($task->fresh()->reminded_at);

        // Chạy lại → không gửi trùng (cờ reminded_at chặn).
        Notification::fake();
        $this->artisan('tasks:dispatch-reminders')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_overdue_command_notifies_assignee_and_watchers_once(): void
    {
        Notification::fake();
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $watcher = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->overdue()->create();
        $task->watchers()->attach($watcher->id);

        $this->artisan('tasks:dispatch-reminders')->assertSuccessful();

        Notification::assertSentTo($sales, TaskNotification::class, fn ($n) => $n->type === 'overdue');
        Notification::assertSentTo($watcher, TaskNotification::class, fn ($n) => $n->type === 'overdue');
        $this->assertNotNull($task->fresh()->overdue_notified_at);
    }

    // ───────────────────── bell / read ─────────────────────

    public function test_notifications_index_and_open_marks_read(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $task = Task::factory()->forUser($sales)->create();

        // Chèn trực tiếp bảng notifications (không qua notify(): tránh
        // ShouldQueueAfterCommit bị nuốt bởi transaction của RefreshDatabase).
        $sales->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => TaskNotification::class,
            'data' => [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'type' => 'assigned',
                'message' => 'Quản lý đã giao cho bạn công việc: '.$task->title,
            ],
        ]);

        $this->actingAs($sales)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee($task->title);

        $noteId = $sales->notifications()->first()->id;

        $this->actingAs($sales)
            ->get(route('notifications.open', $noteId))
            ->assertRedirect(route('tasks.show', $task->id));

        $this->assertNotNull($sales->fresh()->notifications()->first()->read_at);
    }
}
