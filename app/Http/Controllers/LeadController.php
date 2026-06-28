<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Enums\SchoolLevel;
use App\Http\Requests\Lead\StoreLeadRequest;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    public function __construct(private LeadService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Lead::class);

        $filters = $request->only(['status', 'school_level', 'assigned_user_id', 'branch_id']);

        // Chỉ super-admin được phép filter theo branch_id; với role khác,
        // BranchScope đã giới hạn dữ liệu nên bỏ qua filter này để tránh nhầm lẫn.
        if (! $request->user()?->hasRole('super-admin')) {
            unset($filters['branch_id']);
        }

        $leads = $this->service->list($filters);

        $branches = $request->user()?->hasRole('super-admin')
            ? Branch::orderBy('name')->get()
            : collect();

        return view('leads.index', [
            'leads' => $leads,
            'branches' => $branches,
            'statuses' => LeadStatus::cases(),
            'levels' => SchoolLevel::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Lead::class);

        if (! $this->wantsModalForm()) {
            return redirect()->route('leads.index');
        }

        return view('leads.create', [
            'statuses' => LeadStatus::cases(),
            'levels' => SchoolLevel::cases(),
            'branchUsers' => $this->branchUsers($request->user()?->branch_id),
        ]);
    }

    public function store(StoreLeadRequest $request)
    {
        $lead = $this->service->create($request->validated());

        return $this->modalRedirect(route('leads.show', $lead), 'Đã tạo lead.');
    }

    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);
        $lead->load(['branch', 'assignedUser']);

        return view('leads.show', [
            'lead' => $lead,
            'branchUsers' => $this->branchUsers($lead->branch_id),
        ]);
    }

    public function edit(Lead $lead)
    {
        $this->authorize('update', $lead);

        if (! $this->wantsModalForm()) {
            return redirect()->route('leads.show', $lead);
        }

        return view('leads.edit', [
            'lead' => $lead,
            'statuses' => LeadStatus::cases(),
            'levels' => SchoolLevel::cases(),
            'branchUsers' => $this->branchUsers($lead->branch_id),
        ]);
    }

    public function update(UpdateLeadRequest $request, Lead $lead)
    {
        $this->service->update($lead, $request->validated());

        return $this->modalRedirect(route('leads.show', $lead), 'Đã cập nhật lead.');
    }

    public function destroy(Lead $lead)
    {
        $this->authorize('delete', $lead);
        $this->service->delete($lead);

        return redirect()->route('leads.index')
            ->with('success', 'Đã xóa lead.');
    }

    public function assign(Request $request, Lead $lead)
    {
        $this->authorize('assign', $lead);

        $data = $request->validate([
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        try {
            $this->service->assign($lead, $data['assigned_user_id'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('leads.show', $lead)
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
}
