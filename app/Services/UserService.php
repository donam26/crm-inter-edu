<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class UserService
{
    /**
     * Danh sách user trong phạm vi của actor: super-admin xem mọi user,
     * branch-manager chỉ xem user CÙNG branch.
     */
    public function list(User $actor, array $filters = []): LengthAwarePaginator
    {
        return User::query()
            ->with(['roles', 'branch'])
            ->when(
                ! $actor->isSuperAdmin(),
                fn ($q) => $q->where('branch_id', $actor->branch_id),
            )
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(
                fn ($q2) => $q2->where('name', 'like', "%{$v}%")
                    ->orWhere('email', 'like', "%{$v}%")
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    public function create(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data) {
            // branch_id: super-admin chọn tự do; branch-manager ép về branch mình.
            $branchId = $actor->isSuperAdmin()
                ? ($data['branch_id'] ?? null)
                : $actor->branch_id;

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'branch_id' => $branchId,
            ]);

            $this->syncRolesInTeam($user, $branchId, $data['roles'] ?? []);

            return $user->fresh(['roles', 'branch']);
        });
    }

    public function update(User $actor, User $user, array $data): User
    {
        return DB::transaction(function () use ($actor, $user, $data) {
            // Chỉ super-admin được đổi branch của user; branch-manager giữ nguyên.
            $branchId = $actor->isSuperAdmin()
                ? ($data['branch_id'] ?? null)
                : $user->branch_id;

            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'branch_id' => $branchId,
            ];

            if (! empty($data['password'])) {
                $payload['password'] = Hash::make($data['password']);
            }

            $user->update($payload);
            $this->syncRolesInTeam($user, $branchId, $data['roles'] ?? []);

            return $user->fresh(['roles', 'branch']);
        });
    }

    public function delete(User $user): void
    {
        DB::transaction(fn () => $user->delete());
    }

    /**
     * Gán role cho user trong đúng team (branch) của user, chỉ giới hạn ở các
     * role hợp lệ của branch đó (branch-manager không thể gán super-admin hay
     * role của branch khác — names lạ bị loại trước khi sync).
     *
     * @param  list<string>  $requestedRoleNames
     */
    private function syncRolesInTeam(User $user, ?int $branchId, array $requestedRoleNames): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branchId);

        $assignable = Role::query()
            ->when(
                $branchId === null,
                fn ($q) => $q->whereNull('branch_id'),
                fn ($q) => $q->where('branch_id', $branchId),
            )
            ->pluck('name')
            ->all();

        $names = array_values(array_intersect($requestedRoleNames, $assignable));

        $user->syncRoles($names);
    }
}
