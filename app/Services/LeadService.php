<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Lead::query()
            ->with(['branch', 'assignedUser'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['school_level'] ?? null, fn ($q, $v) => $q->where('school_level', $v))
            ->when($filters['assigned_user_id'] ?? null, fn ($q, $v) => $q->where('assigned_user_id', $v))
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();
    }

    public function create(array $data): Lead
    {
        return DB::transaction(function () use ($data) {
            // Service-layer branch_id injection: user thuộc chi nhánh nào thì
            // lead gán chi nhánh đó, bỏ qua mọi branch_id client gửi lên.
            // Super-admin (không có branch) mới được dùng branch_id đã validate
            // từ request (StoreLeadRequest đảm bảo tồn tại trong bảng branches).
            $userBranchId = Auth::user()?->branch_id;
            $branchId = $userBranchId ?? ($data['branch_id'] ?? null);
            $data['branch_id'] = $branchId;

            // Người phụ trách (nếu có) phải thuộc đúng chi nhánh của lead.
            $this->guardAssigneeBranch($data['assigned_user_id'] ?? null, $branchId);

            return Lead::create($data);
        });
    }

    public function update(Lead $lead, array $data): Lead
    {
        return DB::transaction(function () use ($lead, $data) {
            // Chặn override branch_id qua input người dùng.
            unset($data['branch_id']);

            // Người phụ trách mới (nếu có gửi lên) phải cùng chi nhánh với lead.
            if (array_key_exists('assigned_user_id', $data)) {
                $this->guardAssigneeBranch($data['assigned_user_id'], $lead->branch_id);
            }

            $lead->update($data);

            return $lead->fresh();
        });
    }

    public function delete(Lead $lead): void
    {
        DB::transaction(fn () => $lead->delete());
    }

    public function assign(Lead $lead, ?int $userId): Lead
    {
        return DB::transaction(function () use ($lead, $userId) {
            $this->guardAssigneeBranch($userId, $lead->branch_id);

            $lead->update(['assigned_user_id' => $userId]);

            return $lead->fresh();
        });
    }

    /**
     * Đảm bảo người được phụ trách (nếu có) thuộc đúng chi nhánh của lead.
     * Bỏ qua khi null (chưa phân công).
     */
    private function guardAssigneeBranch(?int $userId, ?int $branchId): void
    {
        if ($userId === null) {
            return;
        }

        $assignee = User::find($userId);

        if (! $assignee || (int) $assignee->branch_id !== (int) $branchId) {
            throw ValidationException::withMessages([
                'assigned_user_id' => 'Người được assign phải thuộc cùng chi nhánh với lead.',
            ]);
        }
    }
}
