<x-layouts.app title="Hoá đơn" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Hoá đơn'],
]">
    <x-page-header title="Hoá đơn" />

    {{-- Filters --}}
    <x-card padding="p-4" class="mb-4">
        <form method="GET" action="{{ route('invoices.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-48">
                <x-input name="q" label="Mã" :value="request('q')" margin="" />
            </div>
            <div class="w-44">
                <x-select name="status" label="Trạng thái" placeholder="Tất cả" margin="">
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </x-select>
            </div>
            <div class="w-40">
                <x-input name="from" label="Từ ngày" type="date" :value="request('from')" margin="" />
            </div>
            <div class="w-40">
                <x-input name="to" label="Đến ngày" type="date" :value="request('to')" margin="" />
            </div>
            <x-button type="submit" variant="secondary"><x-icon name="search" class="h-4 w-4" /> Lọc</x-button>
        </form>
    </x-card>

    <x-table :headers="['Mã', 'Khách hàng', 'Tổng', 'Đã thu', 'Trạng thái', 'Phát hành', 'Hạn', '']">
        @forelse ($invoices as $inv)
            <tr>
                <td class="px-4 py-3">
                    <a href="{{ route('invoices.show', $inv) }}" class="font-mono text-xs font-medium text-gray-900 hover:text-brand-700">{{ $inv->code }}</a>
                </td>
                <td class="px-4 py-3">{{ $inv->deal?->customer?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-right tabular-nums">{{ number_format($inv->total_amount) }} đ</td>
                <td class="px-4 py-3 text-right tabular-nums text-green-700">{{ number_format($inv->paid_amount) }} đ</td>
                <td class="px-4 py-3">
                    <x-badge :variant="$inv->status?->badgeVariant() ?? 'secondary'">{{ $inv->status?->label() }}</x-badge>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $inv->issued_at?->format('d/m/Y') ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">
                    @if ($inv->due_at)
                        <span @class(['text-red-600 font-medium' => $inv->isOverdue()])>{{ $inv->due_at->format('d/m/Y') }}</span>
                    @else — @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('invoices.show', $inv) }}" class="text-sm font-medium text-brand-600 hover:underline">Xem</a>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="8" message="Chưa có hoá đơn nào." icon="invoice" />
        @endforelse
    </x-table>

    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.app>
