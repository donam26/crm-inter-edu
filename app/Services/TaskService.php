<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    /**
     * Liệt kê task có filter + phân trang.
     *
     * Filter hỗ trợ:
     *  - status: enum value
     *  - priority: enum value
     *  - type: enum value
     *  - assigned_user_id: int
     *  - lead_id: int
     *  - branch_id: int (chỉ super-admin được dùng — Controller phải check)
     *  - due: 'overdue' | 'today' | 'this_week' | 'upcoming'
     *  - q: search title/description
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->buildQuery($filters)
            // Mở trước, đóng sau; trong cùng nhóm, due_at gần hơn lên trước.
            ->orderByRaw("CASE WHEN status IN ('pending','in_progress') THEN 0 ELSE 1 END")
            ->orderBy('due_at')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Lấy task cho Kanban board, group theo status.
     *
     * Trả về map: status_value => Collection<Task>. Đảm bảo có đủ 4 cột
     * status (kể cả khi rỗng) để view không phải tự khởi tạo.
     *
     * Mỗi cột giới hạn `$perColumn` task để tránh render quá lớn; user lọc
     * tiếp qua filter form nếu cần.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, Collection<int, Task>>
     */
    public function board(array $filters = [], int $perColumn = 200): array
    {
        // Status filter không áp dụng cho board view (board hiển thị tất cả
        // cột); các filter khác (priority/type/assignee/lead/branch/due/q)
        // vẫn áp dụng bình thường.
        $filters = array_diff_key($filters, ['status' => null]);

        $tasks = $this->buildQuery($filters)
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->get();

        $columns = [];
        foreach (TaskStatus::cases() as $status) {
            $columns[$status->value] = new Collection;
        }

        foreach ($tasks as $task) {
            $key = $task->status?->value;
            if ($key === null || ! isset($columns[$key])) {
                continue;
            }
            if ($columns[$key]->count() >= $perColumn) {
                continue;
            }
            $columns[$key]->push($task);
        }

        return $columns;
    }

    /**
     * Query builder dùng chung cho list/board view.
     *
     * @param  array<string, mixed>  $filters
     */
    private function buildQuery(array $filters): Builder
    {
        return Task::query()
            ->with(['branch', 'lead', 'assignee', 'creator', 'labels'])
            ->withCount([
                'checklistItems',
                'checklistItems as checklist_done_count' => fn ($q) => $q->where('is_done', true),
            ])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['assigned_user_id'] ?? null, fn ($q, $v) => $q->where('assigned_user_id', $v))
            ->when($filters['lead_id'] ?? null, fn ($q, $v) => $q->where('lead_id', $v))
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($filters['watching'] ?? null, fn ($q) => $q->whereHas('watchers', fn ($w) => $w->where('users.id', Auth::id())))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($q2) use ($v) {
                $q2->where('title', 'like', "%{$v}%")
                    ->orWhere('description', 'like', "%{$v}%");
            }))
            ->when($filters['due'] ?? null, function ($q, $v) {
                match ($v) {
                    'overdue' => $q->open()->where('due_at', '<', now()),
                    'today' => $q->open()->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]),
                    'this_week' => $q->open()->whereBetween('due_at', [now()->startOfWeek(), now()->endOfWeek()]),
                    'upcoming' => $q->open()->whereBetween('due_at', [now(), now()->addHours(24)]),
                    default => $q,
                };
            });
    }

    /**
     * Tạo task mới.
     *
     * Service-layer injection bắt buộc:
     *  - branch_id luôn lấy từ assignee (cùng branch với người được giao).
     *  - created_by luôn = auth user (không nhận từ input).
     *  - status mặc định Pending nếu input không hợp lệ.
     *  - completed_at / completed_by luôn null khi tạo mới.
     *
     * Cross-branch guards:
     *  - assignee phải tồn tại; nếu auth user không phải super-admin thì
     *    assignee phải cùng branch với auth user.
     *  - lead (nếu có) phải cùng branch với assignee.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Task
    {
        return DB::transaction(function () use ($data) {
            $authUser = Auth::user();
            $assignee = User::findOrFail($data['assigned_user_id']);

            $this->guardAssigneeBranch($authUser, $assignee);

            // branch_id authoritative source = assignee.branch_id.
            $data['branch_id'] = $assignee->branch_id;

            if (! empty($data['lead_id'])) {
                $this->guardLeadBranch((int) $data['lead_id'], (int) $assignee->branch_id);
            } else {
                $data['lead_id'] = null;
            }

            // Service-layer injection: chặn override các field auto-set.
            $data['created_by'] = $authUser?->id;
            $data['completed_at'] = null;
            $data['completed_by'] = null;
            $data['status'] = $this->normalizeOpenStatus($data['status'] ?? null);

            unset($data['completed_at'], $data['completed_by']);
            $data['completed_at'] = null;
            $data['completed_by'] = null;

            return Task::create($data);
        });
    }

    /**
     * Cập nhật task.
     *
     * Đảm bảo:
     *  - branch_id luôn đồng bộ với assignee.branch_id (sau update).
     *  - lead (nếu có) cùng branch với assignee.
     *  - Khi status được set sang Completed qua update: tự động fill
     *    completed_at = now, completed_by = auth user (idempotent — nếu đã
     *    completed thì giữ nguyên).
     *  - Khi status chuyển từ Completed → Open: clear completed_at/by.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $authUser = Auth::user();

            // Chặn user override các field auto-set qua mass-assign.
            unset(
                $data['branch_id'],
                $data['created_by'],
                $data['completed_at'],
                $data['completed_by'],
            );

            // Resolve assignee mới (hoặc giữ nguyên).
            $assignee = isset($data['assigned_user_id'])
                ? User::findOrFail($data['assigned_user_id'])
                : $task->assignee;

            $this->guardAssigneeBranch($authUser, $assignee);

            $newBranchId = (int) $assignee->branch_id;
            $data['branch_id'] = $newBranchId;

            if (array_key_exists('lead_id', $data)) {
                if (! empty($data['lead_id'])) {
                    $this->guardLeadBranch((int) $data['lead_id'], $newBranchId);
                } else {
                    $data['lead_id'] = null;
                }
            } elseif ($task->lead_id !== null) {
                // assignee có thể đã đổi branch → kiểm tra lead hiện tại.
                $this->guardLeadBranch((int) $task->lead_id, $newBranchId);
            }

            // Status transition: Completed/Open phải đồng bộ completed_at/by.
            $newStatus = $data['status'] ?? $task->status?->value;
            if ($newStatus === TaskStatus::Completed->value) {
                if ($task->status !== TaskStatus::Completed) {
                    $data['completed_at'] = now();
                    $data['completed_by'] = $authUser?->id;
                }
            } else {
                if ($task->status === TaskStatus::Completed) {
                    $data['completed_at'] = null;
                    $data['completed_by'] = null;
                }
            }

            $task->update($data);

            return $task->fresh();
        });
    }

    public function delete(Task $task): void
    {
        DB::transaction(fn () => $task->delete());
    }

    /**
     * Đánh dấu task hoàn thành (atomic).
     *
     * Postcondition: status = Completed && completed_at != null && completed_by != null.
     */
    public function complete(Task $task): Task
    {
        return DB::transaction(function () use ($task) {
            if ($task->status === TaskStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'status' => 'Không thể hoàn thành task đã huỷ.',
                ]);
            }

            $task->update([
                'status' => TaskStatus::Completed,
                'completed_at' => $task->completed_at ?? now(),
                'completed_by' => $task->completed_by ?? Auth::id(),
            ]);

            return $task->fresh();
        });
    }

    /**
     * Mở lại task đã hoàn thành (Reopen → Pending), clear completed_at/by.
     */
    public function reopen(Task $task): Task
    {
        return DB::transaction(function () use ($task) {
            $task->update([
                'status' => TaskStatus::Pending,
                'completed_at' => null,
                'completed_by' => null,
            ]);

            return $task->fresh();
        });
    }

    /**
     * Bắt đầu xử lý task (Pending → InProgress). Idempotent: nếu task đã
     * InProgress thì giữ nguyên. Cấm chuyển từ trạng thái đã đóng
     * (Completed/Cancelled) — phải reopen trước.
     */
    public function start(Task $task): Task
    {
        return DB::transaction(function () use ($task) {
            if ($task->status === TaskStatus::InProgress) {
                return $task;
            }

            if ($task->status !== TaskStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ có thể bắt đầu task ở trạng thái Chưa làm.',
                ]);
            }

            $task->update(['status' => TaskStatus::InProgress]);

            return $task->fresh();
        });
    }

    /**
     * Đặt trạng thái task trực tiếp (dùng cho kéo–thả Kanban). Cho phép chuyển
     * tới bất kỳ trạng thái nào trong 4 cột; tự đồng bộ completed_at/by giống
     * update(): set khi → Completed, clear khi rời Completed.
     */
    public function setStatus(Task $task, TaskStatus $status): Task
    {
        return DB::transaction(function () use ($task, $status) {
            $data = ['status' => $status];

            if ($status === TaskStatus::Completed) {
                if ($task->status !== TaskStatus::Completed) {
                    $data['completed_at'] = now();
                    $data['completed_by'] = Auth::id();
                }
            } elseif ($task->status === TaskStatus::Completed) {
                $data['completed_at'] = null;
                $data['completed_by'] = null;
            }

            $task->update($data);

            return $task->fresh();
        });
    }

    // ───────────────────── guards ─────────────────────

    private function guardAssigneeBranch(?User $authUser, User $assignee): void
    {
        if ($assignee->branch_id === null) {
            throw ValidationException::withMessages([
                'assigned_user_id' => 'Người được giao việc phải thuộc một chi nhánh.',
            ]);
        }

        if ($authUser === null) {
            return;
        }

        // Super-admin được phép giao chéo branch (branch_id của task sẽ
        // theo assignee). Các role khác bị giới hạn cùng branch.
        if ($authUser->hasRole('super-admin')) {
            return;
        }

        if ((int) $authUser->branch_id !== (int) $assignee->branch_id) {
            throw ValidationException::withMessages([
                'assigned_user_id' => 'Không thể giao việc cho người ngoài chi nhánh.',
            ]);
        }
    }

    private function guardLeadBranch(int $leadId, int $branchId): void
    {
        $lead = Lead::withoutGlobalScopes()->find($leadId);

        if ($lead === null) {
            throw ValidationException::withMessages([
                'lead_id' => 'Lead không tồn tại.',
            ]);
        }

        if ((int) $lead->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'lead_id' => 'Lead phải thuộc cùng chi nhánh với người được giao.',
            ]);
        }
    }

    private function normalizeOpenStatus(?string $status): string
    {
        return in_array($status, [
            TaskStatus::Pending->value,
            TaskStatus::InProgress->value,
        ], true) ? $status : TaskStatus::Pending->value;
    }
}
