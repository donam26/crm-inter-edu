<x-layouts.app title="Báo cáo doanh thu" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Báo cáo doanh thu'],
]">
    <x-page-header title="Báo cáo doanh thu" :subtitle="$report['from'] . ' → ' . $report['to']" />

    <x-card padding="p-4" class="mb-6">
        <form method="GET" action="{{ route('revenues.report') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-48">
                <x-input name="from" label="Từ ngày" type="date" :value="$report['from']" margin="" />
            </div>
            <div class="w-48">
                <x-input name="to" label="Đến ngày" type="date" :value="$report['to']" margin="" />
            </div>
            <x-button type="submit" variant="primary"><x-icon name="search" class="h-4 w-4" /> Xem báo cáo</x-button>
        </form>
    </x-card>

    {{-- Tổng quan --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-stat-card
            label="Pipeline đang mở"
            :value="number_format($report['pipeline_value']) . ' đ'"
            :hint="$report['open_deals_count'] . ' cơ hội'"
            icon="deals" variant="brand" />
        <x-stat-card
            label="Doanh thu won"
            :value="number_format($report['won_value']) . ' đ'"
            :hint="$report['won_deals_count'] . ' deal thắng'"
            icon="chart" variant="success" />
        <x-stat-card
            label="Đã phát hành"
            :value="number_format($report['invoiced_amount']) . ' đ'"
            hint="Hoá đơn trong khoảng"
            icon="invoice" variant="brand" />
        <x-stat-card
            label="Đã thu"
            :value="number_format($report['collected_amount']) . ' đ'"
            icon="payment" variant="success" />
        <x-stat-card
            label="Công nợ phải thu"
            :value="number_format($report['outstanding_amount']) . ' đ'"
            icon="invoice" variant="warning" />
        <x-stat-card
            label="Quá hạn"
            :value="number_format($report['overdue_amount']) . ' đ'"
            icon="warning" variant="danger" />
    </div>

    {{-- Theo tháng --}}
    <x-card title="Doanh thu theo tháng" class="mb-6">
        @php
            $maxValue = max($report['monthly']->max('won') ?? 0, $report['monthly']->max('collected') ?? 0, 1);
        @endphp
        <div class="space-y-2 text-sm">
            @forelse ($report['monthly'] as $row)
                <div>
                    <div class="flex justify-between">
                        <span class="font-medium">{{ $row['label'] }}</span>
                        <span class="text-gray-600">
                            <span class="text-green-700">Won: {{ number_format($row['won']) }}</span>
                            · <span class="text-brand-600">Đã thu: {{ number_format($row['collected']) }}</span>
                        </span>
                    </div>
                    <div class="w-full bg-gray-100 rounded h-2 mt-1 overflow-hidden flex">
                        <div class="bg-green-500" style="width: {{ ($row['won'] / $maxValue) * 50 }}%"></div>
                        <div class="bg-brand-500" style="width: {{ ($row['collected'] / $maxValue) * 50 }}%"></div>
                    </div>
                </div>
            @empty
                <x-empty-state message="Không có dữ liệu." icon="chart" />
            @endforelse
        </div>
    </x-card>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        {{-- Top sản phẩm --}}
        <x-card title="Top sản phẩm">
            <x-table :headers="['Tên', 'SL', 'Doanh thu']">
                @forelse ($report['top_products'] as $row)
                    <tr>
                        <td class="px-4 py-2">{{ $row['name'] }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $row['quantity'] }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format($row['revenue']) }} đ</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="3" message="Không có dữ liệu." icon="product" />
                @endforelse
            </x-table>
        </x-card>

        {{-- Theo người phụ trách --}}
        <x-card title="Doanh thu theo người phụ trách">
            <x-table :headers="['Tên', 'Doanh thu won']">
                @forelse ($report['by_owner'] as $row)
                    <tr>
                        <td class="px-4 py-2">{{ $row['user']?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format($row['won']) }} đ</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="2" message="Không có dữ liệu." icon="users" />
                @endforelse
            </x-table>
        </x-card>
    </div>

    {{-- Theo branch — super-admin only --}}
    @if (! is_null($report['by_branch'] ?? null))
        <x-card title="Doanh thu theo chi nhánh">
            <x-table :headers="['Chi nhánh', 'Doanh thu won', 'Đã thu']">
                @forelse ($report['by_branch'] as $row)
                    <tr>
                        <td class="px-4 py-2">{{ $row['branch']?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format($row['won']) }} đ</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format($row['collected']) }} đ</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="3" message="Không có dữ liệu." icon="building" />
                @endforelse
            </x-table>
        </x-card>
    @endif
</x-layouts.app>
