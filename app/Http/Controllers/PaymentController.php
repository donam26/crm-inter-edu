<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\RevenueWorkflowException;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Payment::class);

        $filters = $request->only(['method', 'invoice_id', 'branch_id', 'confirmed', 'from', 'to']);

        if (! $request->user()?->hasRole('super-admin')) {
            unset($filters['branch_id']);
        }

        return view('payments.index', [
            'payments' => $this->service->list($filters),
            'methods' => PaymentMethod::cases(),
            'branches' => $request->user()?->hasRole('super-admin')
                ? Branch::orderBy('name')->get()
                : collect(),
            'filters' => $filters,
        ]);
    }

    public function create(Invoice $invoice)
    {
        $this->authorize('create', [Payment::class, $invoice]);

        if (! $this->wantsModalForm()) {
            return redirect()->route('invoices.show', $invoice);
        }

        return view('payments.create', [
            'invoice' => $invoice,
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function store(StorePaymentRequest $request, Invoice $invoice)
    {
        try {
            $payment = $this->service->record($invoice, $request->validated());
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        } catch (RevenueWorkflowException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return $this->modalRedirect(route('payments.show', $payment), 'Đã ghi nhận thanh toán.');
    }

    public function show(Payment $payment)
    {
        $this->authorize('view', $payment);
        $payment->load(['branch', 'invoice.deal.lead', 'creator', 'confirmer']);

        return view('payments.show', compact('payment'));
    }

    public function edit(Payment $payment)
    {
        $this->authorize('update', $payment);

        if (! $this->wantsModalForm()) {
            return redirect()->route('payments.show', $payment);
        }

        return view('payments.edit', [
            'payment' => $payment,
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment)
    {
        try {
            $this->service->update($payment, $request->validated());
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        }

        return $this->modalRedirect(route('payments.show', $payment), 'Đã cập nhật thanh toán.');
    }

    public function destroy(Payment $payment)
    {
        $this->authorize('delete', $payment);
        $invoiceId = $payment->invoice_id;

        $this->service->delete($payment);

        return redirect()->route('invoices.show', $invoiceId)
            ->with('success', 'Đã xoá thanh toán.');
    }

    public function confirm(Payment $payment)
    {
        $this->authorize('confirm', $payment);

        try {
            $this->service->confirm($payment);
        } catch (RevenueWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã xác nhận thanh toán.');
    }
}
