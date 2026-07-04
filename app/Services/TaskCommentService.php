<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskCommentService
{
    /**
     * Thêm bình luận vào task. user_id luôn = auth user (không nhận từ input).
     */
    public function create(Task $task, string $body): TaskComment
    {
        return DB::transaction(fn () => $task->comments()->create([
            'user_id' => Auth::id(),
            'body' => $body,
        ]));
    }

    public function delete(TaskComment $comment): void
    {
        DB::transaction(fn () => $comment->delete());
    }
}
