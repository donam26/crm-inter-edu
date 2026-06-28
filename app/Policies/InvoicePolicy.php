<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class InvoicePolicy
{
    use ChecksBranchOwnership;

    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $this->sameBranch($user, $invoice) || ! $user->can('invoices.view')) {
            return false;
        }

        return $user->can('invoices.view-all')
            || $this->ownsDeal($user, $invoice->deal);
    }

    /** Tạo invoice cho deal: dùng `[$Invoice::class, $deal]`. */
    public function create(User $user, Deal $deal): bool
    {
        if (! $this->sameBranch($user, $deal) || ! $user->can('invoices.create')) {
            return false;
        }

        return $user->can('invoices.view-all') || $this->ownsDeal($user, $deal);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if (! $this->sameBranch($user, $invoice) || ! $user->can('invoices.update')) {
            return false;
        }

        return $user->can('invoices.view-all')
            || $this->ownsDeal($user, $invoice->deal);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->sameBranch($user, $invoice) && $user->can('invoices.delete');
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        if (! $this->sameBranch($user, $invoice) || ! $user->can('invoices.issue')) {
            return false;
        }

        return $user->can('invoices.view-all')
            || $this->ownsDeal($user, $invoice->deal);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $this->sameBranch($user, $invoice) && $user->can('invoices.void');
    }

    private function ownsDeal(User $user, ?Deal $deal): bool
    {
        return $deal !== null
            && ($deal->owner_user_id === $user->id || $deal->created_by === $user->id);
    }
}
