<x-layouts.app title="Chi tiết hoá đơn" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Hoá đơn', 'url' => route('invoices.index')],
    ['label' => $invoice->code],
]">
    <div class="max-w-3xl">
        <x-page-header :title="'Hoá đơn ' . $invoice->code"
            :subtitle="'Deal: ' . ($invoice->deal?->code ?? '—') . ' · Khách hàng: ' . ($invoice->deal?->customer?->name ?? '—')">
            @can('issue', $invoice)
                @if ($invoice->status?->value === 'draft')
                    <form method="POST" action="{{ route('invoices.issue', $invoice) }}" onsubmit="return confirm('Phát hành hoá đơn?');">
                        @csrf <x-button type="submit" variant="primary">Phát hành</x-button>
                    </form>
                @endif
            @endcan
            @can('update', $invoice)
                @if ($invoice->status?->value === 'draft')
                    <x-button variant="secondary" data-modal-form="{{ route('invoices.edit', $invoice) }}" data-modal-title="Sửa hoá đơn">Sửa</x-button>
                @endif
            @endcan
            @can('void', $invoice)
                @if (in_array($invoice->status?->value, ['issued', 'partially_paid', 'overdue']))
                    <form method="POST" action="{{ route('invoices.void', $invoice) }}" onsubmit="return confirm('Huỷ hoá đơn?');">
                        @csrf
                        <input type="hidden" name="reason" value="">
                        <x-button type="submit" variant="danger">Huỷ</x-button>
                    </form>
                @endif
            @endcan
            @can('create', [\App\Models\Payment::class, $invoice])
                @if (in_array($invoice->status?->value, ['issued', 'partially_paid', 'overdue']))
                    <x-button variant="primary" data-modal-form="{{ route('invoices.payments.create', $invoice) }}" data-modal-title="Ghi nhận thanh toán"><x-icon name="plus" class="h-4 w-4" /> Ghi nhận thanh toán</x-button>
                @endif
            @endcan
        </x-page-header>

        <x-card>
            <table class="min-w-full text-sm">
                <thead class="text-gray-500 border-b border-gray-100">
                    <tr>
                        <th class="text-left py-2">Sản phẩm</th>
                        <th class="text-right py-2">SL</th>
                        <th class="text-right py-2">Đơn giá</th>
                        <th class="text-right py-2">VAT</th>
                        <th class="text-right py-2">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->deal?->items?->sortBy('position') ?? [] as $item)
                        <tr class="border-b border-gray-50">
                            <td class="py-2">{{ $item->name }}</td>
                            <td class="py-2 text-right tabular-nums">{{ $item->quantity }}</td>
                            <td class="py-2 text-right tabular-nums">{{ number_format($item->unit_price) }}</td>
                            <td class="py-2 text-right tabular-nums">{{ $item->tax_rate }}%</td>
                            <td class="py-2 text-right tabular-nums">{{ number_format($item->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="text-sm">
                    <tr><td colspan="4" class="text-right py-2 text-gray-500">Tạm tính:</td><td class="text-right py-2 tabular-nums">{{ number_format($invoice->subtotal_amount) }} đ</td></tr>
                    <tr><td colspan="4" class="text-right py-2 text-gray-500">VAT:</td><td class="text-right py-2 tabular-nums">{{ number_format($invoice->tax_amount) }} đ</td></tr>
                    <tr class="font-semibold border-t border-gray-200">
                        <td colspan="4" class="text-right py-2">Tổng:</td>
                        <td class="text-right py-2 text-lg tabular-nums">{{ number_format($invoice->total_amount) }} đ</td>
                    </tr>
                    <tr><td colspan="4" class="text-right py-2 text-green-700">Đã thu:</td><td class="text-right py-2 tabular-nums text-green-700">{{ number_format($invoice->paid_amount) }} đ</td></tr>
                    <tr class="font-semibold">
                        <td colspan="4" class="text-right py-2 text-yellow-700">Còn lại:</td>
                        <td class="text-right py-2 tabular-nums text-yellow-700">{{ number_format($invoice->balance()) }} đ</td>
                    </tr>
                </tfoot>
            </table>
        </x-card>

        <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
            <x-card padding="p-4">
                <div class="text-gray-500">Phát hành: {{ $invoice->issued_at?->format('d/m/Y') ?? '—' }} {{ $invoice->issuer ? '· '.$invoice->issuer->name : '' }}</div>
                <div class="text-gray-500">Hạn: {{ $invoice->due_at?->format('d/m/Y') ?? '—' }}</div>
                @if ($invoice->voided_at)
                    <div class="text-red-600">Huỷ: {{ $invoice->voided_at->format('d/m/Y H:i') }} {{ $invoice->voider ? '· '.$invoice->voider->name : '' }}</div>
                    @if ($invoice->void_reason)<div class="text-red-600">Lý do: {{ $invoice->void_reason }}</div>@endif
                @endif
                @if ($invoice->note)<div class="mt-2 whitespace-pre-line">{{ $invoice->note }}</div>@endif
            </x-card>
        </div>

        {{-- Payments --}}
        <x-card title="Lịch sử thanh toán" class="mt-6">
            @forelse ($invoice->payments as $p)
                <div class="flex items-center justify-between border-b border-gray-100 py-3 last:border-0 text-sm">
                    <div>
                        <a href="{{ route('payments.show', $p) }}" class="font-mono text-brand-600 hover:underline">{{ $p->code }}</a>
                        <div class="text-xs text-gray-500 mt-0.5 flex items-center gap-2">
                            <x-badge variant="secondary">{{ $p->method?->label() }}</x-badge>
                            <span>{{ $p->paid_at?->format('d/m/Y') }}</span>
                            @if ($p->confirmed_at)
                                <x-badge variant="success">Đã xác nhận</x-badge>
                            @else
                                <x-badge variant="warning">Chưa xác nhận</x-badge>
                            @endif
                            @if ($p->reference_no)<span>· Ref: {{ $p->reference_no }}</span>@endif
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-semibold tabular-nums">{{ number_format($p->amount) }} đ</span>
                        @if (! $p->confirmed_at)
                            @can('confirm', $p)
                                <form method="POST" action="{{ route('payments.confirm', $p) }}" class="inline">
                                    @csrf <button class="text-xs text-green-600 hover:text-green-800">Xác nhận</button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </div>
            @empty
                <x-empty-state message="Chưa có thanh toán." icon="payment" />
            @endforelse
        </x-card>

        <div class="mt-6">
            <a href="{{ route('invoices.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline">
                <x-icon name="arrow-left" class="h-4 w-4" /> Quay lại danh sách
            </a>
        </div>
    </div>
</x-layouts.app>
