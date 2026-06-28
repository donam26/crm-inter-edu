<x-layouts.app title="Lịch hẹn" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Lịch hẹn'],
]">
    <x-page-header title="Lịch hẹn">
        <a href="{{ route('events.calendar') }}">
            <x-button variant="secondary"><x-icon name="calendar" class="h-4 w-4" /> Xem theo tháng</x-button>
        </a>
        @can('create', \App\Models\Event::class)
            <x-button variant="primary" data-modal-form="{{ route('events.create') }}" data-modal-title="Tạo lịch hẹn"><x-icon name="plus" class="h-4 w-4" /> Tạo lịch</x-button>
        @endcan
    </x-page-header>

    {{-- Filters --}}
    <x-card padding="p-4" class="mb-6">
        <form method="GET" action="{{ route('events.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-44">
                <x-select name="status" label="Trạng thái" placeholder="Tất cả" margin="">
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </x-select>
            </div>

            <div class="w-44">
                <x-select name="type" label="Loại" placeholder="Tất cả" margin="">
                    @foreach ($types as $t)
                        <option value="{{ $t->value }}" @selected(($filters['type'] ?? '') === $t->value)>{{ $t->label() }}</option>
                    @endforeach
                </x-select>
            </div>

            <div class="w-44">
                <x-input name="from" label="Từ ngày" type="date" :value="$filters['from'] ?? ''" margin="" />
            </div>

            <div class="w-44">
                <x-input name="to" label="Đến ngày" type="date" :value="$filters['to'] ?? ''" margin="" />
            </div>

            @hasrole('branch-manager')
                <div class="w-44">
                    <x-select name="organizer_user_id" label="Người chủ trì" placeholder="Tất cả" margin="">
                        @foreach ($branchUsers as $u)
                            <option value="{{ $u->id }}" @selected((string) ($filters['organizer_user_id'] ?? '') === (string) $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </x-select>
                </div>
            @endhasrole

            @hasrole('super-admin')
                <div class="w-44">
                    <x-select name="branch_id" label="Chi nhánh" placeholder="Tất cả" margin="">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </x-select>
                </div>
            @endhasrole

            <div class="w-64">
                <x-input name="q" label="Tìm kiếm" :value="$filters['q'] ?? ''" placeholder="Tiêu đề / mô tả / địa điểm" margin="" />
            </div>

            <div class="flex gap-2">
                <x-button type="submit" variant="primary"><x-icon name="search" class="h-4 w-4" /> Lọc</x-button>
                <a href="{{ route('events.index') }}">
                    <x-button type="button" variant="secondary">Xoá lọc</x-button>
                </a>
            </div>
        </form>
    </x-card>

    <x-table :headers="['Tiêu đề', 'Người chủ trì', 'Bắt đầu', 'Kết thúc', 'Trạng thái', '']">
        @forelse ($events as $event)
            <tr class="{{ $event->is_overdue ? 'bg-yellow-50' : '' }}">
                <td class="px-4 py-3">
                    <a href="{{ route('events.show', $event) }}" class="font-medium text-brand-600 hover:underline">
                        {{ $event->title }}
                    </a>
                    <div class="text-xs text-gray-500 mt-0.5 flex flex-wrap gap-2 items-center">
                        <x-badge variant="secondary">{{ $event->type?->label() }}</x-badge>
                        @if ($event->is_online)
                            <x-badge variant="primary">Online</x-badge>
                        @elseif ($event->location)
                            <span>· {{ $event->location }}</span>
                        @endif
                        @if ($event->lead)
                            <span>· Lead: <a href="{{ route('leads.show', $event->lead) }}" class="hover:underline">{{ $event->lead->school_name }}</a></span>
                        @endif
                    </div>
                </td>
                <td class="px-4 py-3 text-sm">{{ $event->organizer?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm">{{ $event->starts_at?->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-3 text-sm">{{ $event->ends_at?->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-3">
                    <x-badge :variant="$event->status?->badgeVariant() ?? 'secondary'">
                        {{ $event->status?->label() }}
                    </x-badge>
                    @if ($event->is_overdue)
                        <x-badge variant="warning">Quá hạn</x-badge>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    @can('markDone', $event)
                        @if ($event->status?->isOpen())
                            <form method="POST" action="{{ route('events.done', $event) }}" class="inline">
                                @csrf
                                <button class="text-xs text-green-600 hover:text-green-800">Đã diễn ra</button>
                            </form>
                        @endif
                    @endcan
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="6" message="Chưa có lịch nào." icon="calendar" />
        @endforelse
    </x-table>

    <div class="mt-4">{{ $events->links() }}</div>
</x-layouts.app>
