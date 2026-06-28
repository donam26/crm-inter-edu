<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class UserController extends Controller
{
    public function __construct(private UserService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        return view('users.index', [
            'users' => $this->service->list($request->user(), $request->only(['q'])),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', User::class);

        if (! $this->wantsModalForm()) {
            return redirect()->route('users.index');
        }

        return view('users.create', $this->formData($request->user()));
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->service->create($request->user(), $request->validated());

        return $this->modalRedirect(route('users.show', $user), 'Đã tạo người dùng.');
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);
        $user->load(['roles', 'branch']);

        return view('users.show', compact('user'));
    }

    public function edit(Request $request, User $user)
    {
        $this->authorize('update', $user);

        if (! $this->wantsModalForm()) {
            return redirect()->route('users.show', $user);
        }

        $user->load(['roles', 'branch']);

        return view('users.edit', [
            'user' => $user,
            'assignedRoles' => $user->roles->pluck('name')->all(),
            ...$this->formData($request->user()),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->service->update($request->user(), $user, $request->validated());

        return $this->modalRedirect(route('users.show', $user), 'Đã cập nhật người dùng.');
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);
        $this->service->delete($user);

        return redirect()->route('users.index')
            ->with('success', 'Đã xóa người dùng.');
    }

    /**
     * Dữ liệu dùng chung cho form create/edit: role có thể gán + (super-admin)
     * danh sách branch. Branch-manager chỉ thấy role của branch mình.
     *
     * @return array{roles: Collection, branches: Collection, isSuperAdmin: bool}
     */
    private function formData(User $actor): array
    {
        $isSuperAdmin = $actor->isSuperAdmin();

        $roles = Role::query()
            ->with('branch')
            ->when(! $isSuperAdmin, fn ($q) => $q->where('branch_id', $actor->branch_id))
            ->orderByRaw('branch_id IS NULL DESC')
            ->orderBy('branch_id')
            ->orderBy('name')
            ->get();

        return [
            'roles' => $roles,
            'branches' => $isSuperAdmin ? Branch::orderBy('name')->get() : collect(),
            'isSuperAdmin' => $isSuperAdmin,
        ];
    }
}
