<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Exceptions\RevenueWorkflowException;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Models\Branch;
use App\Models\Deal;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $filters = $request->only(['status', 'deal_id', 'branch_id', 'q', 'from', 'to']);

        if (! $request->user()?->hasRole('super-admin')) {
            unset($filters['branch_id']);
        }

        return view('invoices.index', [
            'invoices' => $this->service->list($filters),
            'statuses' => InvoiceStatus::cases(),
            'branches' => $request->user()?->hasRole('super-admin')
                ? Branch::orderBy('name')->get()
                : collect(),
            'filters' => $filters,
        ]);
    }

    public function create(Deal $deal)
    {
        $this->authorize('create', [Invoice::class, $deal]);

        if (! $this->wantsModalForm()) {
            return redirect()->route('deals.show', $deal);
        }

        return view('invoices.create', compact('deal'));
    }

    public function store(StoreInvoiceRequest $request, Deal $deal)
    {
        try {
            $invoice = $this->service->create($deal, $request->validated());
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        } catch (RevenueWorkflowException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return $this->modalRedirect(route('invoices.show', $invoice), 'Đã tạo hoá đơn (nháp).');
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        $invoice->load([
            'branch', 'deal.lead', 'deal.items',
            'creator', 'issuer', 'voider',
            'payments.creator', 'payments.confirmer',
        ]);

        return view('invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        if (! $this->wantsModalForm()) {
            return redirect()->route('invoices.show', $invoice);
        }

        return view('invoices.edit', compact('invoice'));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        try {
            $this->service->update($invoice, $request->validated());
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        }

        return $this->modalRedirect(route('invoices.show', $invoice), 'Đã cập nhật hoá đơn.');
    }

    public function destroy(Invoice $invoice)
    {
        $this->authorize('delete', $invoice);

        try {
            $this->service->delete($invoice);
        } catch (RevenueWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.index')
            ->with('success', 'Đã xoá hoá đơn nháp.');
    }

    public function issue(Invoice $invoice)
    {
        $this->authorize('issue', $invoice);

        try {
            $this->service->issue($invoice);
        } catch (RevenueWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã phát hành hoá đơn.');
    }

    public function void(Request $request, Invoice $invoice)
    {
        $this->authorize('void', $invoice);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $this->service->void($invoice, $data['reason'] ?? null);
        } catch (RevenueWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã huỷ hoá đơn.');
    }
}
