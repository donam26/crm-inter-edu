@props([
    'title' => null,
    'padding' => 'p-6',
])

{{--
    Card container chuẩn. 3 cách dùng:
      <x-card>...</x-card>                         (chỉ thân, có padding)
      <x-card title="Tiêu đề">...</x-card>         (header + thân)
      <x-card title="...">                         (header có nút hành động)
          <x-slot:actions>...</x-slot:actions>
          ...
      </x-card>
--}}
<div {{ $attributes->merge(['class' => 'bg-white rounded-lg border border-gray-200 shadow-sm']) }}>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-b border-gray-100">
            @if ($title)
                <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
            @endif
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
        <div class="{{ $padding }}">{{ $slot }}</div>
    @else
        <div class="{{ $padding }}">{{ $slot }}</div>
    @endif
</div>
