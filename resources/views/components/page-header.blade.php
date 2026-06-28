@props([
    'title',
    'subtitle' => null,
])

{{-- Tiêu đề trang + vùng nút hành động (slot). Chuẩn hoá cỡ H1 toàn hệ thống. --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="min-w-0">
        <h1 class="text-xl font-semibold text-gray-900 truncate">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-sm text-gray-500 mt-0.5">{{ $subtitle }}</p>
        @endif
    </div>

    @if (! $slot->isEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
