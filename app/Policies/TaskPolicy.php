<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class TaskPolicy
{
    use ChecksBranchOwnership; // before() → super-admin bypass

    public function viewAny(User $user): bool
    {
        return $user->can('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        if (! $this->sameBranch($user, $task) || ! $user->can('tasks.view')) {
            return false;
        }

        // tasks.view-all → mọi task; nếu không → task được giao cho mình hoặc
        // gắn với Lead mình phụ trách.
        return $user->can('tasks.view-all')
            || $task->assigned_user_id === $user->id
            || ($task->lead_id !== null && $task->lead?->assigned_user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('tasks.create');
    }

    public function update(User $user, Task $task): bool
    {
        if (! $this->sameBranch($user, $task) || ! $user->can('tasks.update')) {
            return false;
        }

        return $user->can('tasks.view-all')
            || $task->assigned_user_id === $user->id
            || $task->created_by === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        if (! $this->sameBranch($user, $task) || ! $user->can('tasks.delete')) {
            return false;
        }

        // view-all → xóa tự do trong branch; nếu không → chỉ task do mình tạo.
        return $user->can('tasks.view-all')
            || $task->created_by === $user->id;
    }

    public function complete(User $user, Task $task): bool
    {
        if (! $this->sameBranch($user, $task) || ! $user->can('tasks.update')) {
            return false;
        }

        // Assignee được hoàn thành task của mình; có view-all → mọi task.
        return $user->can('tasks.view-all')
            || $task->assigned_user_id === $user->id;
    }

    public function reopen(User $user, Task $task): bool
    {
        return $this->complete($user, $task);
    }
}
