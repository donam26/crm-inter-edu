<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\Lead;
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
        // Truy cập contact đi theo quyền truy cập Lead cha (own/all qua leads.view-all).
        return $user->can('contacts.view')
            && app(LeadPolicy::class)->view($user, $contact->lead);
    }

    public function create(User $user, ?Lead $lead = null): bool
    {
        if (! $user->can('contacts.create')) {
            return false;
        }

        return $lead instanceof Lead
            ? app(LeadPolicy::class)->update($user, $lead)
            : true;
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->can('contacts.update')
            && app(LeadPolicy::class)->update($user, $contact->lead);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->can('contacts.delete')
            && app(LeadPolicy::class)->update($user, $contact->lead);
    }
}
