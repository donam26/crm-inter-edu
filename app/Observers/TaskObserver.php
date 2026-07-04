<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\TaskNotificationService;
use Illuminate\Support\Facades\Auth;

/**
 * Bắt sự kiện task để bắn thông báo — CHỈ khi có người thao tác đăng nhập
 * (Auth::check()), nên factory/seed/CLI không sinh thông báo rác.
 */
class TaskObserver
{
    public function __construct(private TaskNotificationService $notifier) {}

    public function created(Task $task): void
    {
        if (Auth::check()) {
            $this->notifier->assigned($task);
        }
    }

    public function updated(Task $task): void
    {
        if (! Auth::check()) {
            return;
        }

        if ($task->wasChanged('assigned_user_id')) {
            $this->notifier->assigned($task);
        }

        if ($task->wasChanged('status')) {
            $this->notifier->statusChanged($task);
        }
    }
}
