<?php

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);

        $filters = $request->only(['status', 'assigned_user_id', 'branch_id']);

        // Chỉ super-admin được phép filter theo branch_id; với role khác,
        // BranchScope đã giới hạn dữ liệu nên bỏ qua filter này để tránh nhầm lẫn.
        if (! $request->user()?->hasRole('super-admin')) {
            unset($filters['branch_id']);
        }

        $customers = $this->service->list($filters);

        $branches = $request->user()?->hasRole('super-admin')
            ? Branch::orderBy('name')->get()
            : collect();

        return view('customers.index', [
            'customers' => $customers,
            'branches' => $branches,
            'statuses' => CustomerStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Customer::class);

        if (! $this->wantsModalForm()) {
            return redirect()->route('customers.index');
        }

        $user = $request->user();
        $isSuperAdmin = $user?->hasRole('super-admin') ?? false;

        return view('customers.create', [
            'statuses' => CustomerStatus::cases(),
            // Super-admin chọn chi nhánh ngay trên form → cần user của mọi chi
            // nhánh (kèm branch_id) để lọc người phụ trách theo chi nhánh đã chọn.
            // User thường chỉ thấy người trong chi nhánh của mình.
            'branchUsers' => $isSuperAdmin
                ? $this->assignableUsers()
                : $this->branchUsers($user?->branch_id),
            // Super-admin không thuộc chi nhánh nào → phải tự chọn branch cho customer.
            'branches' => $isSuperAdmin
                ? Branch::orderBy('name')->get()
                : collect(),
        ]);
    }

    public function store(StoreCustomerRequest $request)
    {
        $customer = $this->service->create($request->validated());

        return $this->modalRedirect(route('customers.show', $customer), 'Đã tạo khách hàng.');
    }

    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);
        $customer->load(['branch', 'assignedUser']);

        return view('customers.show', [
            'customer' => $customer,
            'branchUsers' => $this->branchUsers($customer->branch_id),
        ]);
    }

    public function edit(Customer $customer)
    {
        $this->authorize('update', $customer);

        if (! $this->wantsModalForm()) {
            return redirect()->route('customers.show', $customer);
        }

        return view('customers.edit', [
            'customer' => $customer,
            'statuses' => CustomerStatus::cases(),
            'branchUsers' => $this->branchUsers($customer->branch_id),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $this->service->update($customer, $request->validated());

        return $this->modalRedirect(route('customers.show', $customer), 'Đã cập nhật khách hàng.');
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);
        $this->service->delete($customer);

        return redirect()->route('customers.index')
            ->with('success', 'Đã xóa khách hàng.');
    }

    public function assign(Request $request, Customer $customer)
    {
        $this->authorize('assign', $customer);

        $data = $request->validate([
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        try {
            $this->service->assign($customer, $data['assigned_user_id'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Đã cập nhật người phụ trách.');
    }

    private function branchUsers(?int $branchId)
    {
        if ($branchId === null) {
            return collect();
        }

        return User::query()
            ->where('branch_id', $branchId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['sales', 'branch-manager']))
            ->orderBy('name')
            ->get();
    }

    /**
     * Toàn bộ user có thể được phân công (mọi chi nhánh) — dùng cho super-admin
     * để client lọc theo chi nhánh đã chọn.
     */
    private function assignableUsers()
    {
        return User::query()
            ->whereNotNull('branch_id')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['sales', 'branch-manager']))
            ->orderBy('name')
            ->get();
    }
}
