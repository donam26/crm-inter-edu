<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Đảm bảo rate-limit không leak giữa các test (RateLimiter dùng cache backend).
        RateLimiter::clear($this->throttleKey('test@example.com'));
        RateLimiter::clear($this->throttleKey('throttle@example.com'));
    }

    /**
     * Khóa throttle khớp với `LoginRequest::throttleKey()`.
     */
    private function throttleKey(string $email, string $ip = '127.0.0.1'): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ip);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'correct-password',
        ]);

        $this->from(route('login'))->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $this->from(route('login'))->post(route('login'), [
            'email' => 'nope@example.com',
            'password' => 'whatever',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_throttles_after_too_many_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'throttle@example.com',
            'password' => 'correct-password',
        ]);

        // 5 failed attempts → next attempt (kể cả password đúng) bị throttle
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'email' => 'throttle@example.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->post(route('login'), [
            'email' => 'throttle@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertSessionHasErrors('email');

        // Throttle phải chặn cả request có credentials đúng → user vẫn là guest.
        $this->assertGuest();
    }
}
