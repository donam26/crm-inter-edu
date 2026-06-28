@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'disabled' => false,
])

@php
    $base = 'inline-flex items-center justify-center gap-1.5 rounded-md font-medium transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed active:translate-y-px';

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-2.5 text-sm',
    ];

    $variants = [
        'primary'   => 'bg-brand-600 text-white shadow-sm hover:bg-brand-700 focus:ring-brand-500',
        'secondary' => 'bg-white text-gray-700 border border-gray-300 shadow-sm hover:bg-gray-50 focus:ring-brand-500',
        'danger'    => 'bg-red-600 text-white shadow-sm hover:bg-red-700 focus:ring-red-500',
        'success'   => 'bg-green-600 text-white shadow-sm hover:bg-green-700 focus:ring-green-500',
        'ghost'     => 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus:ring-gray-300',
    ];

    $classes = $base
        . ' ' . ($sizes[$size] ?? $sizes['md'])
        . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

<button {{ $attributes->merge(['type' => $type, 'class' => $classes]) }} @disabled($disabled)>
    {{ $slot }}
</button>
