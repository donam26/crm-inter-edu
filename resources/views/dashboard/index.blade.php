@php
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name ?? '')->explode(' ')->last();
@endphp

<x-layouts.app title="Dashboard">
    <x-page-header
        :title="'Xin chào, ' . $firstName . ' 👋'"
        subtitle="Tổng quan hoạt động hệ thống" />

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-stat-card label="Tổng số lead" :value="$stats['total_customers']" icon="customers" variant="brand" />
        <x-stat-card label="Tổng số Contact" :value="$stats['total_contacts']" icon="users" variant="brand" />
        <x-stat-card label="Hoạt động 7 ngày qua" :value="$stats['activities_last_7_days']" icon="chart" variant="neutral" />
        @can('branches.view')
            <x-stat-card label="Số chi nhánh có lead" :value="count($stats['customers_by_branch'] ?? [])" icon="building" variant="neutral" />
        @endcan
    </div>

    {{-- Revenue KPIs --}}
    @can('revenues.view')
        <h2 class="text-base font-semibold text-gray-900 mb-3">Doanh thu</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <x-stat-card
                label="Pipeline đang mở"
                :value="number_format($stats['pipeline_value'] ?? 0) . ' đ'"
                :hint="($stats['open_deals_count'] ?? 0) . ' cơ hội'"
                icon="deals" variant="brand" />
            <x-stat-card
                label="Doanh thu thắng tháng này"
                :value="number_format($stats['won_revenue_this_month'] ?? 0) . ' đ'"
                icon="chart" variant="success" />
            <x-stat-card
                label="Đã thu tháng này"
                :value="number_format($stats['collected_this_month'] ?? 0) . ' đ'"
                icon="payment" variant="success" />
            <x-stat-card
                label="Công nợ phải thu"
                :value="number_format($stats['outstanding_amount'] ?? 0) . ' đ'"
                icon="invoice" variant="warning">
                @if (($stats['overdue_invoices_count'] ?? 0) > 0)
                    <span class="text-red-600 font-medium">{{ $stats['overdue_invoices_count'] }} hoá đơn quá hạn</span>
                @endif
            </x-stat-card>
        </div>
    @endcan

    {{-- Breakdown panels --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-card title="Lead theo trạng thái">
            @forelse ($stats['customers_by_status'] as $status => $count)
                <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                    <x-badge variant="primary">{{ \App\Enums\CustomerStatus::tryFrom($status)?->label() ?? $status }}</x-badge>
                    <span class="font-medium tabular-nums">{{ $count }}</span>
                </div>
            @empty
                <x-empty-state message="Chưa có dữ liệu." />
            @endforelse
        </x-card>

        @can('branches.view')
            <x-card title="Lead theo chi nhánh">
                @forelse ($stats['customers_by_branch'] ?? [] as $branchId => $count)
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                        <span>{{ $branches[$branchId]->name ?? 'Branch #'.$branchId }}</span>
                        <span class="font-medium tabular-nums">{{ $count }}</span>
                    </div>
                @empty
                    <x-empty-state message="Chưa có dữ liệu." />
                @endforelse
            </x-card>
        @endcan
    </div>

    {{-- Task widgets --}}
    @if (isset($stats['my_overdue_tasks']) || isset($stats['my_upcoming_tasks']))
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <x-card>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                        Task quá hạn
                        @if (count($stats['my_overdue_tasks'] ?? []))
                            <x-badge variant="danger">{{ count($stats['my_overdue_tasks']) }}</x-badge>
                        @endif
                    </h2>
                </div>
                @forelse ($stats['my_overdue_tasks'] ?? [] as $task)
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0 text-sm">
                        <div class="min-w-0">
                            <a href="{{ route('tasks.show', $task) }}"
                                class="font-medium text-brand-600 hover:underline">{{ $task->title }}</a>
                            <div class="text-xs text-gray-500 mt-0.5">
                                {{ $task->due_at?->format('d/m/Y H:i') }}
                                @if ($task->customer)
                                    · {{ $task->customer->name }}
                                @endif
                            </div>
                        </div>
                        <x-badge :variant="$task->priority?->badgeVariant() ?? 'secondary'">
                            {{ $task->priority?->label() }}
                        </x-badge>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 py-2">Không có task quá hạn.</p>
                @endforelse
            </x-card>

            <x-card>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                        Task sắp đến hạn (24h)
                        @if (count($stats['my_upcoming_tasks'] ?? []))
                            <x-badge variant="warning">{{ count($stats['my_upcoming_tasks']) }}</x-badge>
                        @endif
                    </h2>
                </div>
                @forelse ($stats['my_upcoming_tasks'] ?? [] as $task)
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0 text-sm">
                        <div class="min-w-0">
                            <a href="{{ route('tasks.show', $task) }}"
                                class="font-medium text-brand-600 hover:underline">{{ $task->title }}</a>
                            <div class="text-xs text-gray-500 mt-0.5">
                                {{ $task->due_at?->format('d/m/Y H:i') }}
                                @if ($task->customer)
                                    · {{ $task->customer->name }}
                                @endif
                            </div>
                        </div>
                        <x-badge :variant="$task->priority?->badgeVariant() ?? 'secondary'">
                            {{ $task->priority?->label() }}
                        </x-badge>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 py-2">Không có task sắp tới.</p>
                @endforelse
            </x-card>
        </div>
    @endif

    {{-- Event widget --}}
    @if (isset($stats['my_upcoming_events']))
        <div class="grid grid-cols-1 gap-4 mt-4">
            <x-card>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                        Lịch hẹn sắp tới (48h)
                        @if (count($stats['my_upcoming_events'] ?? []))
                            <x-badge variant="primary">{{ count($stats['my_upcoming_events']) }}</x-badge>
                        @endif
                    </h2>
                </div>
                @forelse ($stats['my_upcoming_events'] ?? [] as $ev)
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0 text-sm">
                        <div class="min-w-0">
                            <a href="{{ route('events.show', $ev) }}"
                                class="font-medium text-brand-600 hover:underline">{{ $ev->title }}</a>
                            <div class="text-xs text-gray-500 mt-0.5 flex items-center gap-2 flex-wrap">
                                <span>{{ $ev->starts_at?->format('d/m/Y H:i') }} – {{ $ev->ends_at?->format('H:i') }}</span>
                                <x-badge variant="secondary">{{ $ev->type?->label() }}</x-badge>
                                @if ($ev->is_online)
                                    <x-badge variant="primary">Online</x-badge>
                                @endif
                                @if ($ev->customer)
                                    <span>· {{ $ev->customer->name }}</span>
                                @endif
                            </div>
                        </div>
                        <span class="text-xs text-gray-500">{{ $ev->organizer?->name ?? '—' }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 py-2">Không có lịch hẹn sắp tới.</p>
                @endforelse
            </x-card>
        </div>
    @endif
</x-layouts.app>
