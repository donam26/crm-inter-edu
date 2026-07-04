<x-layouts.app title="Thông báo" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Thông báo'],
]">
    <x-page-header title="Thông báo">
        @if (auth()->user()->unreadNotifications->isNotEmpty())
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <x-button type="submit" variant="secondary">Đánh dấu tất cả đã đọc</x-button>
            </form>
        @endif
    </x-page-header>

    <x-card padding="p-0">
        <ul class="divide-y divide-gray-100">
            @forelse ($notifications as $note)
                <li class="{{ $note->read_at ? '' : 'bg-brand-50/40' }}">
                    <a href="{{ route('notifications.open', $note->id) }}" class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition">
                        <span class="mt-1.5 h-2 w-2 flex-none rounded-full {{ $note->read_at ? 'bg-transparent' : 'bg-brand-500' }}"></span>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm text-gray-800">{{ $note->data['message'] ?? 'Cập nhật công việc' }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">{{ $note->created_at->format('d/m/Y H:i') }} · {{ $note->created_at->diffForHumans() }}</div>
                        </div>
                    </a>
                </li>
            @empty
                <li class="px-4 py-12 text-center text-sm text-gray-400">Chưa có thông báo nào.</li>
            @endforelse
        </ul>
    </x-card>

    <div class="mt-4">{{ $notifications->links() }}</div>
</x-layouts.app>
