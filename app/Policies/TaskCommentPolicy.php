<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class TaskCommentPolicy
{
    use ChecksBranchOwnership; // before() → super-admin bypass

    /**
     * Chỉ ai XEM được task mới bình luận được — tái dụng đúng logic
     * TaskPolicy@view (view-all / là assignee / phụ trách lead của task).
     * KHÔNG hạ xuống chỉ sameBranch: người cùng chi nhánh nhưng ngoài phạm vi
     * không mở được task thì cũng không được bình luận.
     */
    public function create(User $user, Task $task): bool
    {
        return app(TaskPolicy::class)->view($user, $task);
    }

    /**
     * Phải xem được task; xoá bình luận của chính mình, có tasks.view-all →
     * xoá được của người khác trong phạm vi.
     */
    public function delete(User $user, TaskComment $comment): bool
    {
        $task = $comment->task;

        if ($task === null || ! app(TaskPolicy::class)->view($user, $task)) {
            return false;
        }

        return $user->can('tasks.view-all') || $comment->user_id === $user->id;
    }
}
