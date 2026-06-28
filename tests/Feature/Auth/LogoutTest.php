<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_logout_invalidates_session_and_protected_routes_become_inaccessible(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'));

        // Sau khi logout, request mới (cùng test client) là guest và bị middleware
        // `auth` chuyển hướng về `/login`.
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_guest_redirected_from_protected_route(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
