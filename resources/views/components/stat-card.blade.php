@props([
    'label',
    'value',
    'icon' => null,
    'variant' => 'brand',
    'hint' => null,
])

@php
    $tones = [
        'brand'   => ['text-brand-600', 'bg-brand-50 text-brand-600'],
        'success' => ['text-green-600', 'bg-green-50 text-green-600'],
        'warning' => ['text-yellow-600', 'bg-yellow-50 text-yellow-600'],
        'danger'  => ['text-red-600', 'bg-red-50 text-red-600'],
        'neutral' => ['text-gray-700', 'bg-gray-100 text-gray-600'],
    ];
    [$valueColor, $iconTone] = $tones[$variant] ?? $tones['brand'];
@endphp

<div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm card-hover">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="text-sm text-gray-500">{{ $label }}</div>
            <div class="mt-2 text-2xl font-bold {{ $valueColor }} tabular-nums truncate">{{ $value }}</div>
            @if ($hint)
                <div class="mt-1 text-xs text-gray-500">{{ $hint }}</div>
            @endif
        </div>
        @if ($icon)
            <span class="shrink-0 inline-flex h-10 w-10 items-center justify-center rounded-lg {{ $iconTone }}">
                <x-icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif
    </div>

    @if (! $slot->isEmpty())
        <div class="mt-2 text-xs">{{ $slot }}</div>
    @endif
</div>
