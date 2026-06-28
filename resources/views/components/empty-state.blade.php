@props([
    'message' => 'Chưa có dữ liệu.',
    'icon' => 'inbox',
])

{{-- Empty state cho danh sách dạng card/panel (không dùng trong <table>). --}}
<div class="flex flex-col items-center justify-center py-10 text-center">
    <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-3">
        <x-icon :name="$icon" class="h-6 w-6" />
    </span>
    <p class="text-sm text-gray-500">{{ $message }}</p>
    @if (! $slot->isEmpty())
        <div class="mt-3">{{ $slot }}</div>
    @endif
</div>
