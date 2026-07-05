<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Customer::query()
            ->with(['branch', 'assignedUser'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['assigned_user_id'] ?? null, fn ($q, $v) => $q->where('assigned_user_id', $v))
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();
    }

    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            // Service-layer branch_id injection: user thuộc chi nhánh nào thì
            // customer gán chi nhánh đó, bỏ qua mọi branch_id client gửi lên.
            // Super-admin (không có branch) mới được dùng branch_id đã validate
            // từ request (StoreCustomerRequest đảm bảo tồn tại trong bảng branches).
            $userBranchId = Auth::user()?->branch_id;
            $branchId = $userBranchId ?? ($data['branch_id'] ?? null);
            $data['branch_id'] = $branchId;

            // Người phụ trách (nếu có) phải thuộc đúng chi nhánh của customer.
            $this->guardAssigneeBranch($data['assigned_user_id'] ?? null, $branchId);

            return Customer::create($data);
        });
    }

    public function update(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data) {
            // Chặn override branch_id qua input người dùng.
            unset($data['branch_id']);

            // Người phụ trách mới (nếu có gửi lên) phải cùng chi nhánh với lead.
            if (array_key_exists('assigned_user_id', $data)) {
                $this->guardAssigneeBranch($data['assigned_user_id'], $customer->branch_id);
            }

            $customer->update($data);

            return $customer->fresh();
        });
    }

    public function delete(Customer $customer): void
    {
        DB::transaction(fn () => $customer->delete());
    }

    public function assign(Customer $customer, ?int $userId): Customer
    {
        return DB::transaction(function () use ($customer, $userId) {
            $this->guardAssigneeBranch($userId, $customer->branch_id);

            $customer->update(['assigned_user_id' => $userId]);

            return $customer->fresh();
        });
    }

    /**
     * Đảm bảo người được phụ trách (nếu có) thuộc đúng chi nhánh của customer.
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
