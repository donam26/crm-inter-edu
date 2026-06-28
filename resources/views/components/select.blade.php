@props([
    'name',
    'label' => null,
    'required' => false,
    'placeholder' => null,
    'margin' => 'mb-4',
])

@php
    $hasError = $errors->has($name);

    $selectClass = 'block w-full rounded-md border bg-white px-3 py-2 text-sm shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-1 '
        . ($hasError
            ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500'
            : 'border-gray-300 text-gray-900 focus:border-brand-500 focus:ring-brand-500');
@endphp

<div class="{{ $margin }}">
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1">
            {{ $label }}
            @if ($required)
                <span class="text-red-500 ml-0.5">*</span>
            @endif
        </label>
    @endif

    <select
        name="{{ $name }}"
        id="{{ $name }}"
        @if ($required) required @endif
        {{ $attributes->merge(['class' => $selectClass]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>

    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
