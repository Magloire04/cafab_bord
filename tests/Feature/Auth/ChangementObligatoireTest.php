<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->motDePasseProvisoire()->create([
        'role' => UserRole::Coach,
        'password' => Hash::make('Provisoire-7!'),
    ]);
});

it('sends a user with a provisional password to the change screen', function () {
    $this->actingAs($this->user)->get(route('dashboard'))->assertRedirect(route('password.changer'));
});

it('answers 403 to JSON requests while the change is pending', function () {
    $this->actingAs($this->user)->getJson(route('etat-seances'))->assertForbidden();
});

it('still lets the user log out', function () {
    $this->actingAs($this->user)->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();
});

it('forces the change even when the user comes back through the remember-me cookie', function () {
    $this->user->forceFill(['remember_token' => 'jeton-de-rappel'])->save();

    $this->withCookie(Auth::guard()->getRecallerName(), $this->user->id.'|jeton-de-rappel|'.$this->user->password)
        ->get(route('dashboard'))
        ->assertRedirect(route('password.changer'));
});

it('shows the change screen', function () {
    $this->actingAs($this->user)->get(route('password.changer'))
        ->assertOk()
        ->assertSee('Choisissez votre mot de passe');
});

it('rejects a password that breaks the rule', function () {
    $this->actingAs($this->user)
        ->put(route('password.changer.update'), ['password' => 'court', 'password_confirmation' => 'court'])
        ->assertSessionHasErrors('password');

    expect($this->user->fresh()->must_change_password)->toBeTrue();
});

it('rejects reusing the provisional password', function () {
    $this->actingAs($this->user)
        ->put(route('password.changer.update'), ['password' => 'Provisoire-7!', 'password_confirmation' => 'Provisoire-7!'])
        ->assertSessionHasErrors('password');
});

it('clears the flag and lets the user in once the password is changed', function () {
    $this->actingAs($this->user)
        ->put(route('password.changer.update'), ['password' => 'Nouveau-Pass1', 'password_confirmation' => 'Nouveau-Pass1'])
        ->assertRedirect(route('dashboard'));

    $this->user->refresh();
    expect($this->user->must_change_password)->toBeFalse();
    expect(Hash::check('Nouveau-Pass1', $this->user->password))->toBeTrue();
    $this->actingAs($this->user)->get(route('dashboard'))->assertOk();
});

it('sends a user without a pending change away from the change screen', function () {
    $normal = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($normal)->get(route('password.changer'))->assertRedirect(route('dashboard'));
});
