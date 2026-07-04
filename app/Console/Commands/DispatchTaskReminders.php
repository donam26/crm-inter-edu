<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Notifications\TaskNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * SLA công việc: quét & bắn thông báo cho 2 mốc, mỗi mốc gửi 1 lần (chống trùng
 * bằng cột reminded_at / overdue_notified_at). Chạy theo lịch (routes/console.php).
 */
class DispatchTaskReminders extends Command
{
    protected $signature = 'tasks:dispatch-reminders';

    protected $description = 'Bắn nhắc theo remind_at và cảnh báo task quá hạn';

    /** @var array<int, string> */
    private const OPEN = [TaskStatus::Pending->value, TaskStatus::InProgress->value];

    public function handle(): int
    {
        $reminders = $this->dispatchReminders();
        $overdue = $this->dispatchOverdue();

        $this->info("Đã gửi {$reminders} nhắc hạn, {$overdue} cảnh báo quá hạn.");

        return self::SUCCESS;
    }

    /** Nhắc theo remind_at (đã tới, task còn mở, chưa nhắc). */
    private function dispatchReminders(): int
    {
        $count = 0;

        Task::withoutGlobalScopes()
            ->where('reminder_enabled', true)
            ->whereNotNull('remind_at')
            ->where('remind_at', '<=', now())
            ->whereNull('reminded_at')
            ->whereIn('status', self::OPEN)
            ->with('assignee')
            ->chunkById(100, function ($tasks) use (&$count) {
                foreach ($tasks as $task) {
                    $task->assignee?->notify(new TaskNotification(
                        $task->id, $task->title, 'reminder',
                    ));
                    $task->forceFill(['reminded_at' => now()])->saveQuietly();
                    $count++;
                }
            });

        return $count;
    }

    /** Task quá hạn (còn mở, due_at đã qua, chưa báo) → assignee + watcher. */
    private function dispatchOverdue(): int
    {
        $count = 0;

        Task::withoutGlobalScopes()
            ->whereIn('status', self::OPEN)
            ->where('due_at', '<', now())
            ->whereNull('overdue_notified_at')
            ->with(['assignee', 'watchers'])
            ->chunkById(100, function ($tasks) use (&$count) {
                foreach ($tasks as $task) {
                    $recipients = $task->watchers
                        ->push($task->assignee)
                        ->filter()
                        ->unique('id');

                    if ($recipients->isNotEmpty()) {
                        Notification::send($recipients, new TaskNotification(
                            $task->id, $task->title, 'overdue',
                        ));
                    }

                    $task->forceFill(['overdue_notified_at' => now()])->saveQuietly();
                    $count++;
                }
            });

        return $count;
    }
}
