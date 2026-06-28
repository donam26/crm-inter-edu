@props([
    'type' => 'info',
    'dismissible' => false,
])

@php
    $variants = [
        'success' => ['bg-green-50 text-green-800 border-green-200', 'check'],
        'error'   => ['bg-red-50 text-red-800 border-red-200', 'x-mark'],
        'warning' => ['bg-yellow-50 text-yellow-800 border-yellow-200', 'warning'],
        'info'    => ['bg-blue-50 text-blue-800 border-blue-200', 'info-circle'],
    ];

    [$class, $icon] = $variants[$type] ?? $variants['info'];
@endphp

<div
    x-data="{ show: true }"
    x-show="show"
    x-transition.opacity.duration.300ms
    @if ($dismissible)
        x-init="setTimeout(() => show = false, 4500)"
    @endif
    role="alert"
    {{ $attributes->merge(['class' => 'animate-fade-in-up border rounded-md px-4 py-3 mb-4 flex items-start gap-3 text-sm ' . $class]) }}
>
    <x-icon :name="$icon" class="h-5 w-5 mt-0.5 shrink-0" />

    <div class="flex-1 pr-2">{{ $slot }}</div>

    @if ($dismissible)
        <button
            type="button"
            @click="show = false"
            class="ml-2 text-current opacity-60 hover:opacity-100 transition"
        >
            <span class="sr-only">Đóng</span>
            <x-icon name="x-mark" class="h-4 w-4" />
        </button>
    @endif
</div>
