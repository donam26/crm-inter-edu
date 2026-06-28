<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function __construct(private TaskService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Task::class);

        $filters = $request->only([
            'status', 'priority', 'type', 'assigned_user_id',
            'lead_id', 'branch_id', 'due', 'q',
        ]);

        // Sales chỉ thấy task của chính mình hoặc task của Lead mình phụ trách.
        // Force filter ở Service-layer query là đủ; Policy@view chặn show của
        // task không thuộc phạm vi.
        if ($request->user()?->hasRole('sales')) {
            $filters['assigned_user_id'] = $request->user()->id;
        }

        if (! $request->user()?->hasRole('super-admin')) {
            unset($filters['branch_id']);
        }

        // View mode: 'kanban' (default) | 'list'. Các giá trị khác fallback
        // về kanban để tránh URL injection lạ.
        $view = $request->string('view')->toString();
        $view = in_array($view, ['kanban', 'list'], true) ? $view : 'kanban';

        $shared = [
            'view' => $view,
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'types' => TaskType::cases(),
            'branches' => $request->user()?->hasRole('super-admin')
                ? Branch::orderBy('name')->get()
                : collect(),
            'branchUsers' => $this->branchUsers($request->user()),
            'filters' => $filters,
        ];

        if ($view === 'kanban') {
            return view('tasks.index', $shared + [
                'columns' => $this->service->board($filters),
                'tasks' => null,
            ]);
        }

        return view('tasks.index', $shared + [
            'tasks' => $this->service->list($filters),
            'columns' => null,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Task::class);

        if (! $this->wantsModalForm()) {
            return redirect()->route('tasks.index');
        }

        return view('tasks.create', [
            'priorities' => TaskPriority::cases(),
            'types' => TaskType::cases(),
            'branchUsers' => $this->branchUsers($request->user()),
            'leads' => $this->branchLeads($request->user()),
            'preselectedLeadId' => $request->integer('lead_id') ?: null,
        ]);
    }

    public function store(StoreTaskRequest $request)
    {
        try {
            $task = $this->service->create($request->validated());
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        }

        return $this->modalRedirect(route('tasks.show', $task), 'Đã tạo task.');
    }

    public function show(Task $task)
    {
        $this->authorize('view', $task);
        $task->load(['branch', 'lead', 'assignee', 'creator', 'completer']);

        return view('tasks.show', compact('task'));
    }

    public function edit(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        if (! $this->wantsModalForm()) {
            return redirect()->route('tasks.show', $task);
        }

        return view('tasks.edit', [
            'task' => $task,
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'types' => TaskType::cases(),
            'branchUsers' => $this->branchUsers($request->user(), $task->branch_id),
            'leads' => $this->branchLeads($request->user(), $task->branch_id),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        try {
            $this->service->update($task, $request->validated());
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        }

        return $this->modalRedirect(route('tasks.show', $task), 'Đã cập nhật task.');
    }

    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);
        $this->service->delete($task);

        return redirect()->route('tasks.index')
            ->with('success', 'Đã xoá task.');
    }

    public function complete(Task $task)
    {
        $this->authorize('complete', $task);

        try {
            $this->service->complete($task);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Đã đánh dấu hoàn thành.');
    }

    public function reopen(Task $task)
    {
        $this->authorize('reopen', $task);
        $this->service->reopen($task);

        return back()->with('success', 'Đã mở lại task.');
    }

    public function start(Task $task)
    {
        // Tái dụng quyền `complete`: chỉ assignee hoặc branch-manager mới
        // được transition trạng thái task ngoài việc qua form edit.
        $this->authorize('complete', $task);

        try {
            $this->service->start($task);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Đã chuyển task sang Đang làm.');
    }

    /**
     * Đổi trạng thái task qua kéo–thả Kanban. Tái dụng quyền `complete`.
     */
    public function updateStatus(Request $request, Task $task)
    {
        $this->authorize('complete', $task);

        $data = $request->validate([
            'status' => ['required', Rule::in(TaskStatus::values())],
        ]);

        $this->service->setStatus($task, TaskStatus::from($data['status']));

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Đã cập nhật trạng thái task.');
    }

    /**
     * Tập user có thể được giao task: cùng branch với auth user, role
     * sales/branch-manager. Super-admin: tất cả user thuộc branch tương ứng.
     */
    private function branchUsers(?User $user, ?int $forceBranchId = null)
    {
        if ($user === null) {
            return collect();
        }

        $branchId = $forceBranchId ?? $user->branch_id;

        if ($user->hasRole('super-admin') && $branchId === null) {
            return User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['sales', 'branch-manager']))
                ->orderBy('name')
                ->get();
        }

        if ($branchId === null) {
            return collect();
        }

        return User::query()
            ->where('branch_id', $branchId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['sales', 'branch-manager']))
            ->orderBy('name')
            ->get();
    }

    private function branchLeads(?User $user, ?int $forceBranchId = null)
    {
        if ($user === null) {
            return collect();
        }

        $branchId = $forceBranchId ?? $user->branch_id;

        // Super-admin: nếu không có branchId xác định → trả tất cả lead
        // (BranchScope đã bypass cho super-admin).
        $query = Lead::query()->orderBy('school_name');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->limit(500)->get(['id', 'school_name', 'branch_id']);
    }
}
