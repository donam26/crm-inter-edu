<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class ActivityPolicy
{
    use ChecksBranchOwnership; // before() → super-admin bypass

    public function viewAny(User $user): bool
    {
        return $user->can('activities.view');
    }

    public function view(User $user, Activity $activity): bool
    {
        // Truy cập activity đi theo quyền truy cập Lead cha (own/all qua leads.view-all).
        return $user->can('activities.view')
            && app(LeadPolicy::class)->view($user, $activity->lead);
    }

    public function create(User $user, ?Lead $lead = null): bool
    {
        if (! $user->can('activities.create')) {
            return false;
        }

        return $lead instanceof Lead
            ? app(LeadPolicy::class)->update($user, $lead)
            : true;
    }

    public function update(User $user, Activity $activity): bool
    {
        return $user->can('activities.update')
            && app(LeadPolicy::class)->update($user, $activity->lead);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $user->can('activities.delete')
            && app(LeadPolicy::class)->update($user, $activity->lead);
    }
}
