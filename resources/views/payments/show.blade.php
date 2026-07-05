<x-layouts.app title="Chi tiết thanh toán" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Thanh toán', 'url' => route('payments.index')],
    ['label' => $payment->code],
]">
    <div class="max-w-2xl">
        <x-page-header :title="'Thanh toán ' . $payment->code">
            @can('confirm', $payment)
                @if (! $payment->confirmed_at)
                    <form method="POST" action="{{ route('payments.confirm', $payment) }}">
                        @csrf <x-button type="submit" variant="primary">Xác nhận</x-button>
                    </form>
                @endif
            @endcan
            @can('update', $payment)
                <x-button variant="secondary" data-modal-form="{{ route('payments.edit', $payment) }}" data-modal-title="Sửa thanh toán">Sửa</x-button>
            @endcan
            @can('delete', $payment)
                <form method="POST" action="{{ route('payments.destroy', $payment) }}" onsubmit="return confirm('Xoá thanh toán?');" class="inline">
                    @csrf @method('DELETE')
                    <x-button type="submit" variant="danger">Xoá</x-button>
                </form>
            @endcan
        </x-page-header>

        <x-card>
            <dl class="space-y-3 text-sm">
                <div class="flex"><span class="w-40 text-gray-500">Trạng thái:</span>
                    @if ($payment->confirmed_at)
                        <x-badge variant="success">Đã xác nhận</x-badge>
                    @else
                        <x-badge variant="warning">Chưa xác nhận</x-badge>
                    @endif
                </div>
                <div class="flex"><span class="w-40 text-gray-500">Hoá đơn:</span>
                    <a href="{{ route('invoices.show', $payment->invoice) }}" class="font-mono text-brand-600 hover:underline">{{ $payment->invoice?->code }}</a>
                </div>
                <div class="flex"><span class="w-40 text-gray-500">Lead:</span><span>{{ $payment->invoice?->deal?->customer?->name ?? '—' }}</span></div>
                <div class="flex"><span class="w-40 text-gray-500">Số tiền:</span><span class="font-semibold tabular-nums">{{ number_format($payment->amount) }} đ</span></div>
                <div class="flex"><span class="w-40 text-gray-500">Phương thức:</span><x-badge variant="secondary">{{ $payment->method?->label() }}</x-badge></div>
                <div class="flex"><span class="w-40 text-gray-500">Ngày thu:</span><span>{{ $payment->paid_at?->format('d/m/Y') }}</span></div>
                <div class="flex"><span class="w-40 text-gray-500">Mã giao dịch:</span><span>{{ $payment->reference_no ?? '—' }}</span></div>
                <div class="flex"><span class="w-40 text-gray-500">Người tạo:</span><span>{{ $payment->creator?->name ?? '—' }}</span></div>
                <div class="flex"><span class="w-40 text-gray-500">Người xác nhận:</span><span>{{ $payment->confirmer?->name ?? '—' }} {{ $payment->confirmed_at ? '· '.$payment->confirmed_at->format('d/m/Y H:i') : '' }}</span></div>
                <div class="flex"><span class="w-40 text-gray-500">Ghi chú:</span><span class="whitespace-pre-line">{{ $payment->note ?? '—' }}</span></div>
            </dl>
        </x-card>

        <div class="mt-6">
            <a href="{{ route('invoices.show', $payment->invoice) }}" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline">
                <x-icon name="arrow-left" class="h-4 w-4" /> Về hoá đơn
            </a>
        </div>
    </div>
</x-layouts.app>
