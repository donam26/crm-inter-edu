<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_redirects_guest_to_login(): void
    {
        // `/` chuyển hướng theo trạng thái auth: guest → login, đã đăng nhập →
        // dashboard. Với guest, đây là một redirect (302), không phải 200.
        $response = $this->get('/');

        $response->assertRedirect();
    }
}
