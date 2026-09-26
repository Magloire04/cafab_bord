<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_disabled(): void
    {
        $this->assertFalse(Route::has('register'));

        $response = $this->get('/register');

        $response->assertNotFound();
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_users_can_authenticate_with_an_email_typed_in_capitals_with_spaces(): void
    {
        $user = User::factory()->create(['email' => 'coach@cafab.bj']);

        $this->post('/login', ['email' => '  Coach@CAFAB.bj ', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_email_sent_as_an_array_gets_a_validation_error_not_a_server_error(): void
    {
        $this->from('/login')
            ->post('/login', ['email' => ['x@cafab.bj'], 'password' => 'password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_login_screen_has_a_working_password_toggle(): void
    {
        $this->get('/login')->assertSee('data-toggle-password="password"', false);
    }
}
