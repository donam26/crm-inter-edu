<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class UserPolicy
{
    use ChecksBranchOwnership; // before() → super-admin bypass (quản lý mọi user)

    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        // Branch-manager chỉ xem user CÙNG branch. Super-admin (branch null) đã
        // bypass qua before(); sameBranch cũng tự chặn việc xem super-admin.
        return $user->can('users.view') && $this->sameBranch($user, $target);
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('users.update') && $this->sameBranch($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can('users.delete')
            && $this->sameBranch($user, $target)
            && $user->id !== $target->id; // không tự xóa chính mình
    }
}
