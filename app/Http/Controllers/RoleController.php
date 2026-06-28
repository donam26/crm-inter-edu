<?php

namespace App\Http\Controllers;

use App\Exceptions\RoleInUseException;
use App\Exceptions\RoleIsSystemException;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Role;
use App\Services\RoleService;
use App\Support\PermissionCatalog;

class RoleController extends Controller
{
    public function __construct(private RoleService $service) {}

    public function index()
    {
        $this->authorize('viewAny', Role::class);

        return view('roles.index', [
            'roles' => $this->service->list(auth()->user()),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Role::class);

        if (! $this->wantsModalForm()) {
            return redirect()->route('roles.index');
        }

        return view('roles.create', [
            'groups' => PermissionCatalog::groupsFor(auth()->user()),
        ]);
    }

    public function store(StoreRoleRequest $request)
    {
        $this->service->create($request->user(), $request->validated());

        return $this->modalRedirect(route('roles.index'), 'Đã tạo vai trò.');
    }

    public function edit(Role $role)
    {
        $this->authorize('update', $role);

        if (! $this->wantsModalForm()) {
            return redirect()->route('roles.index');
        }

        return view('roles.edit', [
            'role' => $role,
            'groups' => PermissionCatalog::groupsFor(auth()->user()),
            'assigned' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $this->service->update($request->user(), $role, $request->validated());

        return $this->modalRedirect(route('roles.index'), 'Đã cập nhật vai trò.');
    }

    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);

        try {
            $this->service->delete($role);
        } catch (RoleIsSystemException|RoleInUseException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('roles.index')->with('success', 'Đã xóa vai trò.');
    }
}
