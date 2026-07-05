<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class EventPolicy
{
    use ChecksBranchOwnership; // before() → super-admin bypass

    public function viewAny(User $user): bool
    {
        return $user->can('events.view');
    }

    public function view(User $user, Event $event): bool
    {
        if (! $this->sameBranch($user, $event) || ! $user->can('events.view')) {
            return false;
        }

        // events.view-all → mọi event trong branch; nếu không → event mình tổ
        // chức/tạo, gắn Customer mình phụ trách, hoặc được mời.
        if ($user->can('events.view-all')) {
            return true;
        }

        if ($event->organizer_user_id === $user->id || $event->created_by === $user->id) {
            return true;
        }

        if ($event->customer_id !== null && $event->customer?->assigned_user_id === $user->id) {
            return true;
        }

        return $event->attendees()->where('users.id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('events.create');
    }

    public function update(User $user, Event $event): bool
    {
        if (! $this->sameBranch($user, $event) || ! $user->can('events.update')) {
            return false;
        }

        return $user->can('events.view-all')
            || $event->organizer_user_id === $user->id
            || $event->created_by === $user->id;
    }

    public function delete(User $user, Event $event): bool
    {
        if (! $this->sameBranch($user, $event) || ! $user->can('events.delete')) {
            return false;
        }

        return $user->can('events.view-all')
            || $event->created_by === $user->id;
    }

    public function markDone(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    public function cancel(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    public function respond(User $user, Event $event): bool
    {
        if (! $this->sameBranch($user, $event)) {
            return false;
        }

        // Chỉ attendee được mời mới có quyền phản hồi.
        return $event->attendees()->where('users.id', $user->id)->exists();
    }
}
