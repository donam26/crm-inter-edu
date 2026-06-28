<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait ChecksBranchOwnership
{
    /** Super-admin bypass tự động qua before(). */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super-admin') ? true : null;
    }

    protected function sameBranch(User $user, Model $model): bool
    {
        return $user->branch_id !== null
            && (int) $user->branch_id === (int) $model->branch_id;
    }
}
