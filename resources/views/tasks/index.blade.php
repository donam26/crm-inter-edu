<x-layouts.app title="Công việc" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Công việc'],
]">
    <x-page-header title="Công việc">
        @can('create', \App\Models\Task::class)
            <x-button variant="primary" data-modal-form="{{ route('tasks.create') }}" data-modal-title="Tạo task"><x-icon name="plus" class="h-4 w-4" /> Tạo task</x-button>
        @endcan
    </x-page-header>

    {{-- View mode tabs: kanban (default) | list. Giữ nguyên filter trong URL khi đổi tab. --}}
    @php
        // Build URL cho từng tab, giữ lại tất cả filter hiện hành.
        $kanbanUrl = route('tasks.index', array_merge(request()->query(), ['view' => 'kanban']));
        $listUrl = route('tasks.index', array_merge(request()->query(), ['view' => 'list']));

        $tabBase = 'inline-flex items-center px-4 py-2 text-sm font-medium border-b-2 -mb-px transition';
        $tabActive = 'border-brand-600 text-brand-700';
        $tabIdle = 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';
    @endphp
    <div class="border-b border-gray-200 mb-6">
        <nav class="flex gap-2" aria-label="Chế độ hiển thị">
            <a href="{{ $kanbanUrl }}"
                class="{{ $tabBase }} {{ $view === 'kanban' ? $tabActive : $tabIdle }}"
                aria-current="{{ $view === 'kanban' ? 'page' : 'false' }}">
                <span class="mr-1">▦</span> Kanban
            </a>
            <a href="{{ $listUrl }}"
                class="{{ $tabBase }} {{ $view === 'list' ? $tabActive : $tabIdle }}"
                aria-current="{{ $view === 'list' ? 'page' : 'false' }}">
                <span class="mr-1">☰</span> Danh sách
            </a>
        </nav>
    </div>

    @include('tasks._filters')

    @if ($view === 'kanban')
        @include('tasks._kanban')
    @else
        @include('tasks._list')
    @endif
</x-layouts.app>
