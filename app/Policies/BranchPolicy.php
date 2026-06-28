<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class BranchPolicy
{
    use ChecksBranchOwnership; // before() → super-admin bypass

    public function viewAny(User $user): bool
    {
        // Quản lý danh sách chi nhánh chỉ dành cho super-admin (qua before).
        return $user->can('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        // Branch-manager chỉ xem chi nhánh của mình; super-admin xem mọi chi nhánh.
        return $user->branch_id === $branch->id;
    }

    public function create(User $user): bool
    {
        return $user->can('branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can('branches.update');
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->can('branches.delete');
    }
}
