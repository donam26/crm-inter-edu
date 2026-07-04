<?php

namespace App\Services;

use App\Models\Task;
use App\Notifications\TaskNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

/**
 * Điều phối thông báo + watcher cho task (dùng chung bởi TaskObserver +
 * TaskCommentService). Người thao tác (Auth) không tự nhận thông báo của mình.
 */
class TaskNotificationService
{
    /**
     * Task được giao (mới tạo / đổi assignee): assignee tự thành watcher, và
     * nhận thông báo nếu không phải người đang thao tác.
     */
    public function assigned(Task $task): void
    {
        $assignee = $task->assignee;
        if ($assignee === null) {
            return;
        }

        $task->watchers()->syncWithoutDetaching([$assignee->id]);

        if ($assignee->id !== Auth::id()) {
            $assignee->notify(new TaskNotification(
                $task->id, $task->title, 'assigned', Auth::user()?->name,
            ));
        }
    }

    public function statusChanged(Task $task): void
    {
        $this->notifyWatchers($task, 'status_changed');
    }

    /**
     * Có bình luận mới: người bình luận tự thành watcher; thông báo cho các
     * watcher còn lại.
     */
    public function commented(Task $task): void
    {
        if (Auth::id() !== null) {
            $task->watchers()->syncWithoutDetaching([Auth::id()]);
        }

        $this->notifyWatchers($task, 'commented');
    }

    private function notifyWatchers(Task $task, string $type): void
    {
        $recipients = $task->watchers()
            ->when(Auth::id(), fn ($q, $id) => $q->where('users.id', '!=', $id))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new TaskNotification(
            $task->id, $task->title, $type, Auth::user()?->name,
        ));
    }
}
