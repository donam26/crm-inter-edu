<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class CustomerPolicy
{
    use ChecksBranchOwnership; // before() → super-admin bypass

    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        if (! $this->sameBranch($user, $customer) || ! $user->can('customers.view')) {
            return false;
        }

        // customers.view-all → xem mọi customer trong branch; nếu không → chỉ customer của mình.
        return $user->can('customers.view-all')
            || $customer->assigned_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        if (! $this->sameBranch($user, $customer) || ! $user->can('customers.update')) {
            return false;
        }

        return $user->can('customers.view-all')
            || $customer->assigned_user_id === $user->id;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->sameBranch($user, $customer) && $user->can('customers.delete');
    }

    public function assign(User $user, Customer $customer): bool
    {
        return $this->sameBranch($user, $customer) && $user->can('customers.assign');
    }
}
