<x-layouts.guest title="Đặt lại mật khẩu">
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-gray-900">Đặt lại mật khẩu</h2>
        <p class="text-sm text-gray-500 mt-0.5">Nhập mật khẩu mới cho tài khoản của bạn.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <x-input name="email" label="Email" type="email" :value="$email" required />
        <x-input name="password" label="Mật khẩu mới" type="password" required />
        <x-input name="password_confirmation" label="Xác nhận mật khẩu" type="password" required />

        <x-button type="submit" variant="primary" size="lg" class="w-full">Đặt lại mật khẩu</x-button>
    </form>
</x-layouts.guest>
