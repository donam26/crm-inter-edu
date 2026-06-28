<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class DealPolicy
{
    use ChecksBranchOwnership;

    public function viewAny(User $user): bool
    {
        return $user->can('deals.view');
    }

    public function view(User $user, Deal $deal): bool
    {
        if (! $this->sameBranch($user, $deal) || ! $user->can('deals.view')) {
            return false;
        }

        // deals.view-all → mọi deal; nếu không → deal mình owner hoặc tạo.
        return $user->can('deals.view-all')
            || $deal->owner_user_id === $user->id
            || $deal->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('deals.create');
    }

    public function update(User $user, Deal $deal): bool
    {
        if (! $this->sameBranch($user, $deal) || ! $user->can('deals.update')) {
            return false;
        }

        return $user->can('deals.view-all')
            || $deal->owner_user_id === $user->id
            || $deal->created_by === $user->id;
    }

    public function delete(User $user, Deal $deal): bool
    {
        return $this->sameBranch($user, $deal) && $user->can('deals.delete');
    }

    /** Đóng deal (win/lose). */
    public function close(User $user, Deal $deal): bool
    {
        if (! $this->sameBranch($user, $deal) || ! $user->can('deals.close')) {
            return false;
        }

        return $user->can('deals.view-all')
            || $deal->owner_user_id === $user->id
            || $deal->created_by === $user->id;
    }
}
