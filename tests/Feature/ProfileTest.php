<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed_in_french(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Informations du profil')
            ->assertSee('Mot de passe actuel')
            ->assertDontSee('Update Password');
    }

    public function test_the_name_can_be_changed_without_the_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', ['name' => 'Prudence Aïvodji', 'email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('Prudence Aïvodji', $user->fresh()->name);
    }

    public function test_changing_the_email_requires_the_current_password(): void
    {
        $user = User::factory()->create(['email' => 'ancien@cafab.bj']);

        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => 'nouveau@cafab.bj'])
            ->assertSessionHasErrors('current_password');

        $this->assertSame('ancien@cafab.bj', $user->fresh()->email);
    }

    public function test_changing_the_email_with_a_wrong_password_is_refused(): void
    {
        $user = User::factory()->create(['email' => 'ancien@cafab.bj']);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'nouveau@cafab.bj',
            'current_password' => 'mauvais',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame('ancien@cafab.bj', $user->fresh()->email);
    }

    public function test_the_email_is_changed_normalized_with_the_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => '  Nouveau@CAFAB.bj ',
            'current_password' => 'password',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertSame('nouveau@cafab.bj', $user->fresh()->email);
    }

    public function test_an_email_used_by_another_account_is_refused(): void
    {
        User::factory()->create(['email' => 'pris@cafab.bj']);
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'Pris@cafab.bj',
            'current_password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_saving_shows_a_french_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->followingRedirects()
            ->patch('/profile', ['name' => 'Nouveau nom', 'email' => $user->email])
            ->assertSee('Profil enregistré.')
            ->assertDontSee('profile-updated');
    }
}
