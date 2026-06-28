<?php

namespace App\Services;

use App\Exceptions\RoleInUseException;
use App\Exceptions\RoleIsSystemException;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    /**
     * Danh sách role theo phạm vi của actor: super-admin xem mọi role (kể cả
     * role toàn cục), branch-manager chỉ xem role của branch mình.
     */
    public function list(User $actor): LengthAwarePaginator
    {
        return Role::query()
            ->with('branch')
            ->withCount(['permissions', 'users'])
            ->when(
                ! $actor->isSuperAdmin(),
                fn ($q) => $q->where('branch_id', $actor->branch_id),
            )
            ->orderByRaw('branch_id IS NULL DESC') // role toàn cục lên đầu
            ->orderBy('branch_id')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Tạo role tùy chỉnh trong phạm vi branch của actor (super-admin → role
     * toàn cục). Chỉ gán permission mà actor được phép.
     */
    public function create(User $actor, array $data): Role
    {
        return DB::transaction(function () use ($actor, $data) {
            $branchId = $actor->isSuperAdmin() ? null : $actor->branch_id;
            app(PermissionRegistrar::class)->setPermissionsTeamId($branchId);

            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => 'web',
                'is_system' => false,
            ]);

            $role->syncPermissions($this->allowedPermissions($actor, $data['permissions'] ?? []));

            return $role->fresh();
        });
    }

    public function update(User $actor, Role $role, array $data): Role
    {
        $this->assertNotSystem($role);

        return DB::transaction(function () use ($actor, $role, $data) {
            $role->update(['name' => $data['name']]);
            $role->syncPermissions($this->allowedPermissions($actor, $data['permissions'] ?? []));

            return $role->fresh();
        });
    }

    public function delete(Role $role): void
    {
        $this->assertNotSystem($role);

        DB::transaction(function () use ($role) {
            // Chặn xóa khi role đang được gán cho người dùng (tránh mất quyền ngầm).
            $assigned = DB::table('model_has_roles')->where('role_id', $role->id)->exists();
            if ($assigned) {
                throw new RoleInUseException('Vai trò đang được gán cho người dùng, không thể xóa.');
            }

            $role->delete();
        });
    }

    private function assertNotSystem(Role $role): void
    {
        if ($role->is_system) {
            throw new RoleIsSystemException('Không thể sửa/xóa vai trò hệ thống.');
        }
    }

    /**
     * Lọc danh sách permission gửi lên về đúng tập actor được phép gán
     * (branch-manager không được gán quyền toàn cục như branches.*).
     *
     * @param  list<string>  $requested
     * @return list<string>
     */
    private function allowedPermissions(User $actor, array $requested): array
    {
        $allowed = $actor->isSuperAdmin()
            ? PermissionCatalog::all()
            : PermissionCatalog::branchAssignable();

        return array_values(array_intersect($requested, $allowed));
    }
}
