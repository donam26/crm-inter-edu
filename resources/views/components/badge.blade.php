@props([
    'variant' => 'secondary',
    'dot' => false,
])

@php
    $variants = [
        'primary'   => 'bg-brand-100 text-brand-800',
        'success'   => 'bg-green-100 text-green-800',
        'warning'   => 'bg-yellow-100 text-yellow-800',
        'danger'    => 'bg-red-100 text-red-800',
        'info'      => 'bg-blue-100 text-blue-800',
        'secondary' => 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-200',
    ];

    $dotColor = [
        'primary'   => 'bg-brand-500',
        'success'   => 'bg-green-500',
        'warning'   => 'bg-yellow-500',
        'danger'    => 'bg-red-500',
        'info'      => 'bg-blue-500',
        'secondary' => 'bg-gray-400',
    ];

    $class = $variants[$variant] ?? $variants['secondary'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap ' . $class]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full {{ $dotColor[$variant] ?? $dotColor['secondary'] }}"></span>
    @endif
    {{ $slot }}
</span>
