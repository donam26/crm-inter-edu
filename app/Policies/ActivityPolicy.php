<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\Customer;
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
        // Truy cập activity đi theo quyền truy cập Customer cha (own/all qua customers.view-all).
        return $user->can('activities.view')
            && app(CustomerPolicy::class)->view($user, $activity->customer);
    }

    public function create(User $user, ?Customer $customer = null): bool
    {
        if (! $user->can('activities.create')) {
            return false;
        }

        return $customer instanceof Customer
            ? app(CustomerPolicy::class)->update($user, $customer)
            : true;
    }

    public function update(User $user, Activity $activity): bool
    {
        return $user->can('activities.update')
            && app(CustomerPolicy::class)->update($user, $activity->customer);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $user->can('activities.delete')
            && app(CustomerPolicy::class)->update($user, $activity->customer);
    }
}
