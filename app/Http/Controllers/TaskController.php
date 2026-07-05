<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Models\Branch;
use App\Models\Label;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\LabelService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

class TaskController extends Controller
{
    public function __construct(
        private TaskService $service,
        private LabelService $labels,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Task::class);

        $filters = $request->only([
            'status', 'priority', 'type', 'assigned_user_id',
            'lead_id', 'branch_id', 'due', 'q', 'watching',
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
        $task->load([
            'branch', 'lead', 'assignee', 'creator', 'completer',
            'labels', 'checklistItems', 'comments.author', 'watchers',
        ]);

        return view('tasks.show', [
            'task' => $task,
            'activityFeed' => $this->taskActivityFeed($task),
            'branchLabels' => Label::where('branch_id', $task->branch_id)
                ->orderBy('name')
                ->get(),
            'isWatching' => $task->watchers->contains(Auth::id()),
        ]);
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
     * Tập user có thể được giao task: mọi thành viên CÙNG branch với auth user
     * (không giới hạn theo vai trò — ràng buộc branch do TaskService kiểm: người
     * được giao phải có branch_id và, trừ super-admin, cùng branch người tạo).
     * Super-admin: mọi user đã thuộc một chi nhánh.
     */
    private function branchUsers(?User $user, ?int $forceBranchId = null)
    {
        if ($user === null) {
            return collect();
        }

        $branchId = $forceBranchId ?? $user->branch_id;

        if ($user->hasRole('super-admin') && $branchId === null) {
            return User::query()
                ->whereNotNull('branch_id')
                ->orderBy('name')
                ->get();
        }

        if ($branchId === null) {
            return collect();
        }

        return User::query()
            ->where('branch_id', $branchId)
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

    /**
     * Gán/bỏ nhãn cho task (form inline ở trang chi tiết). Chỉ nhãn cùng branch
     * với task được chấp nhận (guard trong LabelService).
     */
    public function syncLabels(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'label_ids' => ['array'],
            'label_ids.*' => ['integer'],
        ]);

        $this->labels->sync($task, $data['label_ids'] ?? []);

        return back()->with('success', 'Đã cập nhật nhãn.');
    }

    public function watch(Task $task)
    {
        $this->authorize('view', $task);
        $task->watchers()->syncWithoutDetaching([Auth::id()]);

        return back()->with('success', 'Đang theo dõi công việc này.');
    }

    public function unwatch(Task $task)
    {
        $this->authorize('view', $task);
        $task->watchers()->detach(Auth::id());

        return back()->with('success', 'Đã bỏ theo dõi công việc này.');
    }

    /**
     * Dòng thời gian audit cho task: đọc activity_log (spatie), rút gọn thành
     * cấu trúc phẳng cho view (ai · thời điểm · các thay đổi old→new).
     *
     * @return array<int, array{causer: string, time: Carbon, event: string, changes: array<int, array{label: string, from: string, to: string}>}>
     */
    private function taskActivityFeed(Task $task): array
    {
        $labels = [
            'title' => 'Tiêu đề',
            'status' => 'Trạng thái',
            'priority' => 'Ưu tiên',
            'assigned_user_id' => 'Người được giao',
            'due_at' => 'Hạn chót',
            'start_at' => 'Ngày bắt đầu',
        ];

        $logs = Activity::where('subject_type', $task->getMorphClass())
            ->where('subject_id', $task->getKey())
            ->with('causer')
            ->latest()
            ->limit(50)
            ->get();

        // Batch-fetch tên user tham chiếu bởi assigned_user_id (old + new) trên
        // toàn bộ log — tránh N+1 khi có nhiều lần đổi người được giao.
        $userIds = $logs->flatMap(fn (Activity $log) => [
            $log->properties['attributes']['assigned_user_id'] ?? null,
            $log->properties['old']['assigned_user_id'] ?? null,
        ])->filter()->unique()->values();

        $userNames = $userIds->isEmpty()
            ? collect()
            : User::withoutGlobalScopes()->whereIn('id', $userIds)->pluck('name', 'id');

        return $logs
            ->map(function (Activity $log) use ($labels, $userNames) {
                $new = $log->properties['attributes'] ?? [];
                $old = $log->properties['old'] ?? [];

                $changes = [];
                foreach ($new as $field => $value) {
                    if (! isset($labels[$field])) {
                        continue;
                    }
                    $changes[] = [
                        'label' => $labels[$field],
                        'from' => $this->formatActivityValue($field, $old[$field] ?? null, $userNames),
                        'to' => $this->formatActivityValue($field, $value, $userNames),
                    ];
                }

                return [
                    'causer' => $log->causer?->name ?? 'Hệ thống',
                    'time' => $log->created_at,
                    'event' => (string) $log->event,
                    'changes' => $changes,
                ];
            })
            ->filter(fn (array $row) => $row['event'] === 'created' || $row['changes'] !== [])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, string>  $userNames  map id => name (đã batch-fetch)
     */
    private function formatActivityValue(string $field, mixed $value, Collection $userNames): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($field) {
            'status' => TaskStatus::tryFrom((string) $value)?->label() ?? (string) $value,
            'priority' => TaskPriority::tryFrom((string) $value)?->label() ?? (string) $value,
            'assigned_user_id' => $userNames[$value] ?? "#{$value}",
            'due_at', 'start_at' => Carbon::parse((string) $value)->format('d/m/Y H:i'),
            default => (string) $value,
        };
    }
}
