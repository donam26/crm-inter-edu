<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class LeadPolicy
{
    use ChecksBranchOwnership; // before() → super-admin bypass

    public function viewAny(User $user): bool
    {
        return $user->can('leads.view');
    }

    public function view(User $user, Lead $lead): bool
    {
        if (! $this->sameBranch($user, $lead) || ! $user->can('leads.view')) {
            return false;
        }

        // leads.view-all → xem mọi lead trong branch; nếu không → chỉ lead của mình.
        return $user->can('leads.view-all')
            || $lead->assigned_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('leads.create');
    }

    public function update(User $user, Lead $lead): bool
    {
        if (! $this->sameBranch($user, $lead) || ! $user->can('leads.update')) {
            return false;
        }

        return $user->can('leads.view-all')
            || $lead->assigned_user_id === $user->id;
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $this->sameBranch($user, $lead) && $user->can('leads.delete');
    }

    public function assign(User $user, Lead $lead): bool
    {
        return $this->sameBranch($user, $lead) && $user->can('leads.assign');
    }
}
