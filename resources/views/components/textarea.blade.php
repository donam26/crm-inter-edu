@props([
    'name',
    'label' => null,
    'required' => false,
    'rows' => 3,
    'value' => null,
    'margin' => 'mb-4',
])

@php
    $hasError = $errors->has($name);

    $textareaClass = 'block w-full rounded-md border px-3 py-2 text-sm shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-1 '
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

    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        {{ $attributes->merge(['class' => $textareaClass]) }}
    >{{ old($name, $value) }}</textarea>

    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
