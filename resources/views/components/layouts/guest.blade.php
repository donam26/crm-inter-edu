<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'CRM Inter-Edu' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-brand-50 via-gray-50 to-white text-gray-900 antialiased">
    <div class="w-full max-w-md animate-fade-in-up">
        <div class="flex flex-col items-center text-center mb-8">
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-white text-xl font-bold shadow-lg shadow-brand-600/20">IE</span>
            <h1 class="text-2xl font-bold text-gray-900 mt-4">CRM Inter-Edu</h1>
            <p class="text-sm text-gray-500 mt-1">Hệ thống quản lý quan hệ khách hàng</p>
        </div>

        @if (session('status'))
            <x-alert type="info" dismissible>{{ session('status') }}</x-alert>
        @endif
        @if (session('success'))
            <x-alert type="success" dismissible>{{ session('success') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="error" dismissible>{{ session('error') }}</x-alert>
        @endif

        <div class="bg-white shadow-sm rounded-xl p-6 sm:p-8 border border-gray-200">
            {{ $slot }}
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">
            &copy; {{ date('Y') }} Inter-Edu. Bảo lưu mọi quyền.
        </p>
    </div>
</body>
</html>
