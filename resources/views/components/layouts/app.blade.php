<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'CRM Inter-Edu' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900" x-data="{ sidebarOpen: false }">
    <div class="flex min-h-screen">
        <x-sidebar :open="'sidebarOpen'" />

        <div class="flex min-w-0 flex-1 flex-col">
            <x-topbar :title="$title ?? null" :breadcrumbs="$breadcrumbs ?? []" @toggle-sidebar="sidebarOpen = !sidebarOpen" />

            <main class="flex-1 p-4 sm:p-6">
                <div class="mx-auto w-full max-w-7xl">
                    @if (session('success'))
                        <x-alert type="success" dismissible>{{ session('success') }}</x-alert>
                    @endif
                    @if (session('error'))
                        <x-alert type="error" dismissible>{{ session('error') }}</x-alert>
                    @endif

                    <div class="animate-fade-in-up">
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </div>

    {{-- Modal dùng chung cho tạo/sửa mọi module (nội dung nạp qua AJAX) --}}
    <div id="app-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div data-modal-backdrop class="fixed inset-0 bg-black/40"></div>
        <div class="flex min-h-full items-start justify-center p-4 sm:py-10">
            <div data-modal-panel class="relative w-full max-w-2xl bg-white rounded-xl shadow-xl">
                <div class="flex items-center justify-between gap-3 px-6 py-4 border-b border-gray-100">
                    <h3 data-modal-title class="text-base font-semibold text-gray-900"></h3>
                    <button type="button" data-modal-close class="text-gray-400 hover:text-gray-600 transition">
                        <span class="sr-only">Đóng</span>
                        <x-icon name="x-mark" class="h-5 w-5" />
                    </button>
                </div>
                <div data-modal-body class="px-6 py-5"></div>
            </div>
        </div>
    </div>
</body>
</html>
