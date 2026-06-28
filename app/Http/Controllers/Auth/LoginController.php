<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Hiển thị form đăng nhập.
     */
    public function show(): View
    {
        return view('auth.login');
    }

    /**
     * Xử lý submit đăng nhập, regenerate session để tránh session fixation,
     * và redirect tới `intended` URL hoặc `/dashboard`.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
