@props([
    'name',
    'label' => null,
    'type' => 'text',
    'required' => false,
    'value' => null,
    'placeholder' => null,
    'margin' => 'mb-4',
])

@php
    $hasError = $errors->has($name);

    $inputClass = 'block w-full rounded-md border px-3 py-2 text-sm shadow-sm transition placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-1 '
        . ($hasError
            ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500'
            : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500');
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

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ old($name, $value) }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($required) required @endif
        {{ $attributes->merge(['class' => $inputClass]) }}
    />

    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
