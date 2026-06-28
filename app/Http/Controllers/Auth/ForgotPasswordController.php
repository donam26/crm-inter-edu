<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    /**
     * Hiển thị form nhập email để gửi link đặt lại mật khẩu.
     */
    public function show(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Gửi link reset password qua `Password::sendResetLink`.
     *
     * Chú ý: luôn flash cùng một thông báo trung lập bất kể email tồn tại
     * hay không, để không tiết lộ sự tồn tại của tài khoản (Requirement 3.7).
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($request->only('email'));

        return back()->with(
            'status',
            __('Nếu email tồn tại trong hệ thống, link đặt lại mật khẩu đã được gửi.'),
        );
    }
}
