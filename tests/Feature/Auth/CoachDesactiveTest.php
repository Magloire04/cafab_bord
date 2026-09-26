<?php

use App\Enums\StatutPersonne;
use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(fn () => $this->messageDesactive = "Ce compte est désactivé. Contactez l'administrateur.");

it('refuses the login of a deactivated coach with a French message', function () {
    $coach = Coach::factory()->create(['statut' => StatutPersonne::Inactif]);

    $this->from(route('login'))
        ->post(route('login'), ['email' => $coach->user->email, 'password' => 'password', 'remember' => 'on'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => $this->messageDesactive])
        ->assertCookieMissing(Auth::guard()->getRecallerName());

    $this->assertGuest();
});

it('still lets an active coach log in', function () {
    $coach = Coach::factory()->create(['statut' => StatutPersonne::Actif]);

    $this->post(route('login'), ['email' => $coach->user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($coach->user);
});

it('does not concern an admin or a coach account without a coach profile', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $sansProfil = User::factory()->create(['role' => UserRole::Coach]);

    $this->post(route('login'), ['email' => $admin->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($admin);
    $this->actingAs($admin)->get(route('dashboard'))->assertOk();

    $this->post(route('logout'));

    $this->post(route('login'), ['email' => $sansProfil->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($sansProfil);
    $this->actingAs($sansProfil)->get(route('dashboard'))->assertOk();
});

it('logs out an open session of a coach deactivated since, on the next page', function () {
    $coach = Coach::factory()->create(['statut' => StatutPersonne::Actif]);
    $this->actingAs($coach->user)->get(route('dashboard'))->assertOk();

    $coach->update(['statut' => StatutPersonne::Inactif]);

    $this->get(route('plannings.index'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => $this->messageDesactive]);

    $this->assertGuest();
});

it('logs out a deactivated coach rather than sending him to the password change screen', function () {
    $coach = Coach::factory()->create(['statut' => StatutPersonne::Inactif]);
    $coach->user->update(['must_change_password' => true]);

    $this->actingAs($coach->user)->get(route('dashboard'))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('answers 403 to the JSON polling of a coach deactivated since', function () {
    $coach = Coach::factory()->create(['statut' => StatutPersonne::Inactif]);

    $this->actingAs($coach->user)->getJson(route('etat-seances'))->assertForbidden();

    $this->assertGuest();
});
