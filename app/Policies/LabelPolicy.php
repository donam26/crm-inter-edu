<?php

namespace App\Policies;

use App\Models\Label;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class LabelPolicy
{
    use ChecksBranchOwnership; // before() → super-admin bypass

    public function viewAny(User $user): bool
    {
        return $user->can('labels.view');
    }

    public function create(User $user): bool
    {
        return $user->can('labels.manage');
    }

    public function update(User $user, Label $label): bool
    {
        return $this->sameBranch($user, $label) && $user->can('labels.manage');
    }

    public function delete(User $user, Label $label): bool
    {
        return $this->sameBranch($user, $label) && $user->can('labels.manage');
    }
}
