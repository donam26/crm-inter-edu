@props([
    'href' => '#',
    'icon' => 'dot',
    'active' => false,
])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    @class([
        'group relative flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
        'bg-brand-50 text-brand-700' => $active,
        'text-gray-600 hover:bg-gray-100 hover:text-gray-900' => ! $active,
    ])
>
    @if ($active)
        <span class="absolute inset-y-1.5 left-0 w-1 rounded-r bg-brand-600"></span>
    @endif
    <x-icon
        :name="$icon"
        @class([
            'h-5 w-5',
            'text-brand-600' => $active,
            'text-gray-400 group-hover:text-gray-600' => ! $active,
        ])
    />
    <span class="truncate">{{ $slot }}</span>
</a>
