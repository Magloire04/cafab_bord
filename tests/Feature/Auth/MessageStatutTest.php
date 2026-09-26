<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;

it('confirms a reset link request in a success callout', function () {
    Notification::fake();
    User::factory()->create(['email' => 'coach@cafab.bj']);

    $this->from(route('password.request'))
        ->followingRedirects()
        ->post(route('password.email'), ['email' => 'coach@cafab.bj'])
        ->assertOk()
        ->assertSee('class="callout-success mt-3" role="status"', false)
        ->assertSee('Si un compte correspond à cette adresse');
});

it('shows the status on the login screen in the same success callout', function () {
    $this->withSession(['status' => 'Votre mot de passe a été réinitialisé.'])
        ->get(route('login'))
        ->assertOk()
        ->assertSee('class="callout-success mt-3" role="status"', false)
        ->assertDontSee('text-green-600', false);
});
