<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskCommentService
{
    public function __construct(private TaskNotificationService $notifier) {}

    /**
     * Thêm bình luận vào task. user_id luôn = auth user (không nhận từ input).
     * Sau khi lưu: người bình luận thành watcher + báo các watcher còn lại.
     */
    public function create(Task $task, string $body): TaskComment
    {
        $comment = DB::transaction(fn () => $task->comments()->create([
            'user_id' => Auth::id(),
            'body' => $body,
        ]));

        $this->notifier->commented($task);

        return $comment;
    }

    public function delete(TaskComment $comment): void
    {
        DB::transaction(fn () => $comment->delete());
    }
}
