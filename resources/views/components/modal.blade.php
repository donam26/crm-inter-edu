@props([
    'name' => 'modal',
    'title' => '',
])

<div
    x-data="{ open: false }"
    x-on:open-modal-{{ $name }}.window="open = true"
    x-on:close-modal-{{ $name }}.window="open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/40"
        @click="open = false"
    ></div>

    {{-- Panel --}}
    <div class="flex min-h-full items-center justify-center p-4">
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative bg-white rounded-lg shadow-xl w-full max-w-lg p-6"
        >
            @if ($title)
                <div class="flex items-start justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
                    <button
                        type="button"
                        @click="open = false"
                        class="text-gray-400 hover:text-gray-600 transition"
                    >
                        <span class="sr-only">Đóng</span>
                        <x-icon name="x-mark" class="h-5 w-5" />
                    </button>
                </div>
            @endif

            <div>{{ $slot }}</div>
        </div>
    </div>
</div>
