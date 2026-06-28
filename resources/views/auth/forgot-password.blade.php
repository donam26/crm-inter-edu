<x-layouts.guest title="Quên mật khẩu">
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-gray-900">Quên mật khẩu</h2>
        <p class="text-sm text-gray-500 mt-0.5">Nhập email của bạn. Nếu email tồn tại trong hệ thống, chúng tôi sẽ gửi link đặt lại mật khẩu.</p>
    </div>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <x-input name="email" label="Email" type="email" required autofocus />

        <x-button type="submit" variant="primary" size="lg" class="w-full">Gửi link đặt lại</x-button>
    </form>

    <div class="mt-4 text-sm">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1 text-brand-600 hover:underline">
            <x-icon name="arrow-left" class="h-4 w-4" /> Quay lại đăng nhập
        </a>
    </div>
</x-layouts.guest>
