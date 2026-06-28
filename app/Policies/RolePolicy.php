<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class RolePolicy
{
    use ChecksBranchOwnership; // before() → super-admin bypass (quản lý mọi role)

    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view') && $this->sameTenant($user, $role);
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.update')
            && $this->sameTenant($user, $role)
            && ! $role->is_system; // role hệ thống bất biến qua UI
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.delete')
            && $this->sameTenant($user, $role)
            && ! $role->is_system;
    }

    /**
     * Role thuộc đúng branch của user. Super-admin (branch null) đã bypass qua
     * before(); branch-manager chỉ thao tác role trong branch mình (loại role
     * toàn cục branch_id=null).
     */
    private function sameTenant(User $user, Role $role): bool
    {
        return $role->branch_id !== null && (int) $role->branch_id === (int) $user->branch_id;
    }
}
