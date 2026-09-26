<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Nouveau-Pass1',
                'password_confirmation' => 'Nouveau-Pass1',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_the_same_message_is_shown_for_an_unknown_address(): void
    {
        Notification::fake();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'inconnu@cafab.bj'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));

        Notification::assertNothingSent();
    }

    public function test_the_known_address_gets_the_same_message(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', __('passwords.sent'));
    }

    public function test_a_reset_link_is_sent_for_an_address_typed_in_capitals(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'coach@cafab.bj']);

        $this->post('/forgot-password', ['email' => '  Coach@CAFAB.bj ']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_resetting_the_password_clears_a_pending_forced_change(): void
    {
        Notification::fake();
        $user = User::factory()->motDePasseProvisoire()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Nouveau-Pass1',
                'password_confirmation' => 'Nouveau-Pass1',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->assertFalse($user->fresh()->must_change_password);
    }
}
