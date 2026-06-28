<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_reset_link_sent_for_existing_user(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'exists@example.com']);

        $this->post(route('password.email'), ['email' => 'exists@example.com'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_no_reset_link_for_unknown_email(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nope@example.com'])
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_response_is_neutral_for_existing_and_unknown_email(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'exists@example.com']);

        $existsResponse = $this->post(route('password.email'), ['email' => 'exists@example.com']);
        $existsStatus = session('status');

        $missingResponse = $this->post(route('password.email'), ['email' => 'nope@example.com']);
        $missingStatus = session('status');

        // HTTP status code phải giống nhau (cả hai đều redirect back với flash).
        $this->assertSame($existsResponse->status(), $missingResponse->status());

        // Flash message `status` phải giống hệt nhau để không tiết lộ
        // sự tồn tại của email trong hệ thống.
        $this->assertNotNull($existsStatus);
        $this->assertNotNull($missingStatus);
        $this->assertSame($existsStatus, $missingStatus);
    }
}
