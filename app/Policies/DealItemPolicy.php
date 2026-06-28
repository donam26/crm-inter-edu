<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\DealItem;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class DealItemPolicy
{
    use ChecksBranchOwnership;

    /**
     * Line item là một phần của Deal → quyền sửa item = quyền sửa deal cha
     * (deals.update + own/all).
     */
    public function create(User $user, Deal $deal): bool
    {
        return $this->canMutateDeal($user, $deal);
    }

    public function update(User $user, DealItem $item): bool
    {
        $deal = $item->deal;

        return $this->sameBranch($user, $item)
            && $deal !== null
            && $this->canMutateDeal($user, $deal);
    }

    public function delete(User $user, DealItem $item): bool
    {
        return $this->update($user, $item);
    }

    private function canMutateDeal(User $user, Deal $deal): bool
    {
        if (! $this->sameBranch($user, $deal) || ! $user->can('deals.update')) {
            return false;
        }

        return $user->can('deals.view-all')
            || $deal->owner_user_id === $user->id
            || $deal->created_by === $user->id;
    }
}
