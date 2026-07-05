<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Exceptions\RevenueWorkflowException;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private InvoiceService $invoices) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['branch', 'invoice.deal.customer', 'creator', 'confirmer'])
            ->when($filters['method'] ?? null, fn ($q, $v) => $q->where('method', $v))
            ->when($filters['invoice_id'] ?? null, fn ($q, $v) => $q->where('invoice_id', $v))
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($filters['confirmed'] ?? null, function ($q, $v) {
                $bool = filter_var($v, FILTER_VALIDATE_BOOLEAN);
                $bool ? $q->whereNotNull('confirmed_at') : $q->whereNull('confirmed_at');
            })
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '<=', $v))
            ->orderByDesc('paid_at')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Ghi nhận một khoản thanh toán cho hoá đơn.
     *
     * Rules:
     *  - Invoice phải đang ở trạng thái Issued/PartiallyPaid/Overdue (không Draft/Void/Paid).
     *  - amount > 0
     *  - Tổng (đã xác nhận + payment mới nếu auto-confirm) ≤ invoice.total_amount.
     *  - Nếu input có `confirm = true` → auto-confirm và đồng bộ invoice ngay.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $data) {
            if (! ($invoice->status instanceof InvoiceStatus) || ! $invoice->status->isOpen()) {
                throw new RevenueWorkflowException(
                    'Chỉ có thể ghi nhận thanh toán cho hoá đơn đã phát hành và chưa hoàn tất.'
                );
            }

            $amount = (int) ($data['amount'] ?? 0);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Số tiền phải lớn hơn 0.',
                ]);
            }

            $autoConfirm = (bool) ($data['confirm'] ?? false);

            // Khi auto-confirm, tổng đã confirm + amount mới không được vượt total.
            if ($autoConfirm) {
                $confirmedSum = (int) $invoice->payments()
                    ->withoutGlobalScopes()
                    ->whereNotNull('confirmed_at')
                    ->sum('amount');

                if ($confirmedSum + $amount > (int) $invoice->total_amount) {
                    throw ValidationException::withMessages([
                        'amount' => 'Tổng tiền đã thu vượt quá tổng hoá đơn.',
                    ]);
                }
            }

            $authUser = Auth::user();

            $payment = Payment::create([
                'branch_id' => $invoice->branch_id,
                'invoice_id' => $invoice->id,
                'created_by' => $authUser?->id,
                'confirmed_by' => $autoConfirm ? $authUser?->id : null,
                'code' => $this->generateCode($invoice->branch_id),
                'amount' => $amount,
                'method' => $data['method'] ?? 'bank_transfer',
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'confirmed_at' => $autoConfirm ? now() : null,
                'reference_no' => $data['reference_no'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            if ($autoConfirm) {
                $this->invoices->syncFromPayments($invoice->fresh());
            }

            return $payment->fresh();
        });
    }

    /**
     * Xác nhận một payment chưa confirm. Đồng bộ invoice sau xác nhận.
     */
    public function confirm(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            if ($payment->confirmed_at !== null) {
                throw new RevenueWorkflowException('Thanh toán đã được xác nhận.');
            }

            $invoice = $payment->invoice()->withoutGlobalScopes()->firstOrFail();

            if (! ($invoice->status instanceof InvoiceStatus) || ! $invoice->status->isOpen()) {
                throw new RevenueWorkflowException(
                    'Hoá đơn không ở trạng thái có thể nhận thêm xác nhận.'
                );
            }

            $confirmedSum = (int) $invoice->payments()
                ->withoutGlobalScopes()
                ->whereNotNull('confirmed_at')
                ->sum('amount');

            if ($confirmedSum + (int) $payment->amount > (int) $invoice->total_amount) {
                throw new RevenueWorkflowException(
                    'Xác nhận sẽ làm tổng thu vượt tổng hoá đơn.'
                );
            }

            $payment->update([
                'confirmed_at' => now(),
                'confirmed_by' => Auth::id(),
            ]);

            $this->invoices->syncFromPayments($invoice);

            return $payment->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data) {
            // Đã xác nhận: chỉ cho sửa note + reference_no.
            if ($payment->confirmed_at !== null) {
                $data = array_intersect_key($data, array_flip(['note', 'reference_no']));
            }

            unset(
                $data['branch_id'], $data['invoice_id'], $data['code'],
                $data['created_by'], $data['confirmed_by'], $data['confirmed_at'],
            );

            $payment->update($data);

            return $payment->fresh();
        });
    }

    public function delete(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice()->withoutGlobalScopes()->firstOrFail();
            $wasConfirmed = $payment->confirmed_at !== null;

            $payment->delete();

            if ($wasConfirmed) {
                $this->invoices->syncFromPayments($invoice);
            }
        });
    }

    private function generateCode(int $branchId): string
    {
        $datePart = now()->format('ymd');
        $countToday = Payment::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return sprintf('PAY-%d-%s-%03d', $branchId, $datePart, $countToday + 1);
    }
}
