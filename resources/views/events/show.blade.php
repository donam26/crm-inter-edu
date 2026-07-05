<x-layouts.app title="Chi tiết lịch hẹn" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Lịch hẹn', 'url' => route('events.index')],
    ['label' => $event->title],
]">
    <div class="max-w-3xl">
        <x-page-header :title="$event->title">
            @can('markDone', $event)
                @if ($event->status?->isOpen())
                    <form method="POST" action="{{ route('events.done', $event) }}" class="inline">
                        @csrf
                        <x-button type="submit" variant="primary">Đã diễn ra</x-button>
                    </form>
                @endif
            @endcan
            @can('cancel', $event)
                @if ($event->status === \App\Enums\EventStatus::Scheduled)
                    <form method="POST" action="{{ route('events.cancel', $event) }}"
                        onsubmit="return confirm('Huỷ lịch này?');" class="inline">
                        @csrf
                        <x-button type="submit" variant="secondary">Huỷ lịch</x-button>
                    </form>
                @endif
            @endcan
            @can('update', $event)
                <x-button variant="secondary" data-modal-form="{{ route('events.edit', $event) }}" data-modal-title="Sửa lịch hẹn">Sửa</x-button>
            @endcan
            @can('delete', $event)
                <form method="POST" action="{{ route('events.destroy', $event) }}"
                    onsubmit="return confirm('Xoá lịch này?');" class="inline">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger">Xoá</x-button>
                </form>
            @endcan
        </x-page-header>

        <div class="flex items-center gap-2 -mt-2 mb-6">
            <x-badge :variant="$event->status?->badgeVariant() ?? 'secondary'">{{ $event->status?->label() }}</x-badge>
            @if ($event->is_overdue)
                <x-badge variant="warning">Quá hạn</x-badge>
            @endif
        </div>

        @if (session('event_conflicts') && count(session('event_conflicts')))
            <x-alert type="warning" dismissible>
                <p class="font-medium">Cảnh báo: lịch trùng giờ với người tham gia.</p>
                <ul class="mt-2 text-xs list-disc pl-4">
                    @foreach (session('event_conflicts') as $c)
                        @if ((int) ($c['id'] ?? 0) !== (int) $event->id)
                            <li>{{ $c['title'] }} ({{ $c['starts_at'] }} – {{ $c['ends_at'] }})</li>
                        @endif
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <x-card class="space-y-3 text-sm">
            <div class="flex"><span class="w-44 text-gray-500">Loại:</span><span>{{ $event->type?->label() }}</span></div>
            <div class="flex"><span class="w-44 text-gray-500">Bắt đầu:</span><span>{{ $event->starts_at?->format('d/m/Y H:i') }}</span></div>
            <div class="flex"><span class="w-44 text-gray-500">Kết thúc:</span><span>{{ $event->ends_at?->format('d/m/Y H:i') }}</span></div>
            <div class="flex"><span class="w-44 text-gray-500">Thời lượng:</span><span>{{ $event->duration_minutes }} phút</span></div>
            @if ($event->is_online)
                <div class="flex"><span class="w-44 text-gray-500">Online URL:</span>
                    @if ($event->online_url)
                        <a href="{{ $event->online_url }}" target="_blank" rel="noopener"
                            class="text-brand-600 hover:underline break-all">{{ $event->online_url }}</a>
                    @else
                        <span>—</span>
                    @endif
                </div>
            @else
                <div class="flex"><span class="w-44 text-gray-500">Địa điểm:</span><span>{{ $event->location ?? '—' }}</span></div>
            @endif
            <div class="flex"><span class="w-44 text-gray-500">Người chủ trì:</span><span>{{ $event->organizer?->name ?? '—' }}</span></div>
            <div class="flex"><span class="w-44 text-gray-500">Người tạo:</span><span>{{ $event->creator?->name ?? '—' }}</span></div>
            <div class="flex"><span class="w-44 text-gray-500">Chi nhánh:</span><span>{{ $event->branch?->name }}</span></div>
            @if ($event->customer)
                <div class="flex"><span class="w-44 text-gray-500">Lead:</span>
                    <a href="{{ route('customers.show', $event->customer) }}" class="text-brand-600 hover:underline">
                        {{ $event->customer->name }}
                    </a>
                </div>
            @endif
            @if ($event->reminder_at)
                <div class="flex"><span class="w-44 text-gray-500">Nhắc lúc:</span><span>{{ $event->reminder_at->format('d/m/Y H:i') }}</span></div>
            @endif
            <div>
                <div class="text-gray-500 mb-1">Mô tả:</div>
                <div class="whitespace-pre-line">{{ $event->description ?? '—' }}</div>
            </div>
        </x-card>

        <x-card :title="'Người tham gia (' . $event->attendees->count() . ')'" class="mt-6">
            @if ($myAttendance)
                @can('respond', $event)
                    <form method="POST" action="{{ route('events.respond', $event) }}" class="mb-4 flex items-end gap-2">
                        @csrf
                        <span class="text-sm text-gray-600 pb-2">Phản hồi của bạn:</span>
                        <div class="w-44">
                            <x-select name="response" margin="">
                                @foreach (['pending' => 'Chưa phản hồi', 'accepted' => 'Tham gia', 'tentative' => 'Có thể', 'declined' => 'Từ chối'] as $val => $lbl)
                                    <option value="{{ $val }}" @selected($myAttendance->pivot->response === $val)>{{ $lbl }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        <x-button type="submit" variant="primary">Cập nhật</x-button>
                    </form>
                @endcan
            @endif

            @forelse ($event->attendees as $a)
                <div class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0 text-sm">
                    <div>
                        <span class="font-medium">{{ $a->name }}</span>
                        <span class="text-xs text-gray-500">· {{ $a->email }}</span>
                    </div>
                    @php
                        $resp = $a->pivot->response;
                        $variant = match ($resp) {
                            'accepted' => 'success',
                            'declined' => 'danger',
                            'tentative' => 'warning',
                            default => 'secondary',
                        };
                        $label = match ($resp) {
                            'accepted' => 'Tham gia',
                            'declined' => 'Từ chối',
                            'tentative' => 'Có thể',
                            default => 'Chưa phản hồi',
                        };
                    @endphp
                    <x-badge :variant="$variant">{{ $label }}</x-badge>
                </div>
            @empty
                <x-empty-state message="Chưa có người được mời." icon="users" />
            @endforelse
        </x-card>

        <div class="mt-6">
            <a href="{{ route('events.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline">
                <x-icon name="arrow-left" class="h-4 w-4" /> Quay lại danh sách
            </a>
        </div>
    </div>
</x-layouts.app>
