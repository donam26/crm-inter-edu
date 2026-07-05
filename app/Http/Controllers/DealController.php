<?php

namespace App\Http\Controllers;

use App\Enums\DealStage;
use App\Exceptions\RevenueWorkflowException;
use App\Http\Requests\Deal\StoreDealRequest;
use App\Http\Requests\Deal\UpdateDealRequest;
use App\Models\Branch;
use App\Models\Deal;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\DealService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DealController extends Controller
{
    public function __construct(private DealService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Deal::class);

        $filters = $request->only(['stage', 'owner_user_id', 'branch_id', 'q']);

        if (! $request->user()?->hasRole('super-admin')) {
            unset($filters['branch_id']);
        }
        if ($request->user()?->hasRole('sales')) {
            $filters['owner_user_id'] = $request->user()->id;
        }

        return view('deals.index', [
            'deals' => $this->service->list($filters),
            'stages' => DealStage::cases(),
            'branches' => $request->user()?->hasRole('super-admin')
                ? Branch::orderBy('name')->get()
                : collect(),
            'branchUsers' => $this->branchUsers($request->user()?->branch_id),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Deal::class);

        if (! $this->wantsModalForm()) {
            return redirect()->route('deals.index');
        }

        // Customer chưa có deal trong cùng branch (1 customer = 1 deal).
        $customers = Customer::query()
            ->whereDoesntHave('deal')
            ->orderBy('name')
            ->limit(500)
            ->get();

        return view('deals.create', [
            'customers' => $customers,
            'branchUsers' => $this->branchUsers($request->user()?->branch_id),
            'preselectedCustomerId' => $request->integer('customer_id') ?: null,
        ]);
    }

    public function store(StoreDealRequest $request)
    {
        try {
            $deal = $this->service->create($request->validated());
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        }

        return $this->modalRedirect(route('deals.show', $deal), 'Đã tạo deal.');
    }

    public function show(Deal $deal)
    {
        $this->authorize('view', $deal);
        $deal->load(['branch', 'customer', 'owner', 'creator', 'items.product', 'invoices']);

        return view('deals.show', [
            'deal' => $deal,
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function edit(Deal $deal)
    {
        $this->authorize('update', $deal);

        if (! $this->wantsModalForm()) {
            return redirect()->route('deals.show', $deal);
        }

        return view('deals.edit', [
            'deal' => $deal,
            'branchUsers' => $this->branchUsers($deal->branch_id),
        ]);
    }

    public function update(UpdateDealRequest $request, Deal $deal)
    {
        try {
            $this->service->update($deal, $request->validated());
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        }

        return $this->modalRedirect(route('deals.show', $deal), 'Đã cập nhật deal.');
    }

    public function destroy(Deal $deal)
    {
        $this->authorize('delete', $deal);

        try {
            $this->service->delete($deal);
        } catch (RevenueWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('deals.index')
            ->with('success', 'Đã xoá deal.');
    }

    public function win(Deal $deal)
    {
        $this->authorize('close', $deal);

        try {
            $this->service->win($deal);
        } catch (RevenueWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã đánh dấu deal thắng.');
    }

    public function lose(Request $request, Deal $deal)
    {
        $this->authorize('close', $deal);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $this->service->lose($deal, $data['reason'] ?? null);
        } catch (RevenueWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã đánh dấu deal mất.');
    }

    public function reopen(Deal $deal)
    {
        $this->authorize('close', $deal);

        try {
            $this->service->reopen($deal);
        } catch (RevenueWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã mở lại deal.');
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
}
