<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class PaymentPolicy
{
    use ChecksBranchOwnership;

    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        if (! $this->sameBranch($user, $payment) || ! $user->can('payments.view')) {
            return false;
        }

        return $user->can('payments.view-all')
            || $this->ownsDeal($user, $payment->invoice?->deal);
    }

    /** Ghi nhận payment cho invoice: dùng `[$Payment::class, $invoice]`. */
    public function create(User $user, Invoice $invoice): bool
    {
        if (! $this->sameBranch($user, $invoice) || ! $user->can('payments.create')) {
            return false;
        }

        return $user->can('payments.view-all') || $this->ownsDeal($user, $invoice->deal);
    }

    public function update(User $user, Payment $payment): bool
    {
        if (! $this->sameBranch($user, $payment) || ! $user->can('payments.update')) {
            return false;
        }

        // view-all → sửa tự do; nếu không → chỉ payment chưa xác nhận do mình tạo.
        return $user->can('payments.view-all')
            || ($payment->confirmed_at === null && $payment->created_by === $user->id);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $this->sameBranch($user, $payment) && $user->can('payments.delete');
    }

    public function confirm(User $user, Payment $payment): bool
    {
        if (! $this->sameBranch($user, $payment) || ! $user->can('payments.confirm')) {
            return false;
        }

        return $user->can('payments.view-all')
            || $this->ownsDeal($user, $payment->invoice?->deal);
    }

    private function ownsDeal(User $user, ?Deal $deal): bool
    {
        return $deal !== null
            && ($deal->owner_user_id === $user->id || $deal->created_by === $user->id);
    }
}
