<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class ContactPolicy
{
    use ChecksBranchOwnership; // before() → super-admin bypass

    public function viewAny(User $user): bool
    {
        return $user->can('contacts.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        // Truy cập contact đi theo quyền truy cập Customer cha (own/all qua customers.view-all).
        return $user->can('contacts.view')
            && app(CustomerPolicy::class)->view($user, $contact->customer);
    }

    public function create(User $user, ?Customer $customer = null): bool
    {
        if (! $user->can('contacts.create')) {
            return false;
        }

        return $customer instanceof Customer
            ? app(CustomerPolicy::class)->update($user, $customer)
            : true;
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->can('contacts.update')
            && app(CustomerPolicy::class)->update($user, $contact->customer);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->can('contacts.delete')
            && app(CustomerPolicy::class)->update($user, $contact->customer);
    }
}
