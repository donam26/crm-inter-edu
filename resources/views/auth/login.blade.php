<x-layouts.guest title="Đăng nhập">
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-gray-900">Đăng nhập</h2>
        <p class="text-sm text-gray-500 mt-0.5">Chào mừng trở lại 👋 Vui lòng nhập thông tin đăng nhập.</p>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <x-input name="email" label="Email" type="email" placeholder="ban@inter-edu.vn" required autofocus />
        <x-input name="password" label="Mật khẩu" type="password" placeholder="••••••••" required />

        <div class="flex items-center justify-between mb-5">
            <label class="inline-flex items-center text-sm text-gray-700">
                <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                <span class="ml-2">Ghi nhớ đăng nhập</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700 hover:underline">
                    Quên mật khẩu?
                </a>
            @endif
        </div>

        <x-button type="submit" variant="primary" size="lg" class="w-full">Đăng nhập</x-button>
    </form>
</x-layouts.guest>
