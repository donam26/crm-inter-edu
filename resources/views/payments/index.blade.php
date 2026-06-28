<x-layouts.app title="Thanh toán" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Thanh toán'],
]">
    <x-page-header title="Thanh toán" />

    {{-- Filters --}}
    <x-card padding="p-4" class="mb-4">
        <form method="GET" action="{{ route('payments.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-44">
                <x-select name="method" label="Phương thức" placeholder="Tất cả" margin="">
                    @foreach ($methods as $m)
                        <option value="{{ $m->value }}" @selected(request('method') === $m->value)>{{ $m->label() }}</option>
                    @endforeach
                </x-select>
            </div>
            <div class="w-44">
                <x-select name="confirmed" label="Trạng thái xác nhận" placeholder="Tất cả" margin="">
                    <option value="1" @selected(request('confirmed') === '1')>Đã xác nhận</option>
                    <option value="0" @selected(request('confirmed') === '0')>Chưa xác nhận</option>
                </x-select>
            </div>
            <div class="w-40">
                <x-input name="from" label="Từ" type="date" :value="request('from')" margin="" />
            </div>
            <div class="w-40">
                <x-input name="to" label="Đến" type="date" :value="request('to')" margin="" />
            </div>
            <x-button type="submit" variant="secondary"><x-icon name="search" class="h-4 w-4" /> Lọc</x-button>
        </form>
    </x-card>

    <x-table :headers="['Mã', 'Hoá đơn', 'Số tiền', 'PT', 'Ngày thu', 'Xác nhận', '']">
        @forelse ($payments as $p)
            <tr>
                <td class="px-4 py-3">
                    <a href="{{ route('payments.show', $p) }}" class="font-mono text-xs font-medium text-gray-900 hover:text-brand-700">{{ $p->code }}</a>
                </td>
                <td class="px-4 py-3 font-mono text-xs">
                    <a href="{{ route('invoices.show', $p->invoice) }}" class="text-brand-600 hover:underline">{{ $p->invoice?->code }}</a>
                </td>
                <td class="px-4 py-3 text-right tabular-nums">{{ number_format($p->amount) }} đ</td>
                <td class="px-4 py-3 text-gray-600">{{ $p->method?->label() }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $p->paid_at?->format('d/m/Y') }}</td>
                <td class="px-4 py-3">
                    @if ($p->confirmed_at)
                        <x-badge variant="success">✓</x-badge>
                    @else
                        <x-badge variant="warning">…</x-badge>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('payments.show', $p) }}" class="text-sm font-medium text-brand-600 hover:underline">Xem</a>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="7" message="Chưa có thanh toán nào." icon="payment" />
        @endforelse
    </x-table>

    <div class="mt-4">{{ $payments->links() }}</div>
</x-layouts.app>
