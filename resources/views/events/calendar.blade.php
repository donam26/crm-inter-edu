@php
    use Illuminate\Support\Carbon;

    $weekDays = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

    // Group event theo ngày (Y-m-d).
    $eventsByDay = $events->groupBy(fn ($e) => $e->starts_at?->format('Y-m-d'));

    $prev = $cursor->copy()->subMonth()->format('Y-m');
    $next = $cursor->copy()->addMonth()->format('Y-m');
    $today = Carbon::today();
@endphp

<x-layouts.app title="Lịch hẹn — Tháng {{ $cursor->format('m/Y') }}" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Lịch hẹn', 'url' => route('events.index')],
    ['label' => 'Lịch'],
]">
    <x-page-header title="Tháng {{ $cursor->format('m/Y') }}">
        <a href="{{ route('events.calendar', ['month' => $prev]) }}">
            <x-button variant="secondary">←</x-button>
        </a>
        <a href="{{ route('events.calendar', ['month' => $next]) }}">
            <x-button variant="secondary">→</x-button>
        </a>
        <a href="{{ route('events.calendar') }}">
            <x-button variant="secondary">Hôm nay</x-button>
        </a>
        <a href="{{ route('events.index') }}">
            <x-button variant="secondary">Danh sách</x-button>
        </a>
        @can('create', \App\Models\Event::class)
            <x-button variant="primary" data-modal-form="{{ route('events.create') }}" data-modal-title="Tạo lịch hẹn"><x-icon name="plus" class="h-4 w-4" /> Tạo lịch</x-button>
        @endcan
    </x-page-header>

    <x-card padding="p-0" class="overflow-hidden">
        <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-200 text-xs font-medium text-gray-500 uppercase">
            @foreach ($weekDays as $w)
                <div class="px-3 py-2 text-center">{{ $w }}</div>
            @endforeach
        </div>

        <div class="grid grid-cols-7">
            @php
                $cur = $from->copy();
            @endphp
            @while ($cur->lte($to))
                @php
                    $dateKey = $cur->format('Y-m-d');
                    $isCurMonth = $cur->month === $cursor->month;
                    $isToday = $cur->isSameDay($today);
                    $dayEvents = $eventsByDay[$dateKey] ?? collect();
                @endphp
                <div class="border-r border-b border-gray-100 min-h-[110px] p-2
                    {{ $isCurMonth ? 'bg-white' : 'bg-gray-50 text-gray-400' }}
                    {{ $isToday ? 'ring-2 ring-brand-300 ring-inset' : '' }}">
                    <div class="text-xs font-medium mb-1">{{ $cur->format('j') }}</div>
                    @foreach ($dayEvents->take(3) as $e)
                        <a href="{{ route('events.show', $e) }}"
                            class="block text-xs truncate px-1.5 py-0.5 mb-0.5 rounded
                                bg-brand-50 text-brand-700 hover:bg-brand-100"
                            title="{{ $e->title }} — {{ $e->starts_at->format('H:i') }}">
                            <span class="font-medium">{{ $e->starts_at->format('H:i') }}</span>
                            {{ $e->title }}
                        </a>
                    @endforeach
                    @if ($dayEvents->count() > 3)
                        <div class="text-xs text-gray-500">+{{ $dayEvents->count() - 3 }} khác</div>
                    @endif
                </div>
                @php $cur->addDay(); @endphp
            @endwhile
        </div>
    </x-card>
</x-layouts.app>
