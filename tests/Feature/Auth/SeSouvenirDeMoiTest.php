<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('sets the remember-me cookie when the box is checked', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => 'on'])
        ->assertCookie(Auth::guard()->getRecallerName());

    expect($user->fresh()->remember_token)->not->toBeNull();
});

it('does not set it when the box is unchecked', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertCookieMissing(Auth::guard()->getRecallerName());
});

it('logs the user back in from the cookie once the session is gone', function () {
    $user = User::factory()->create();
    $recaller = Auth::guard()->getRecallerName();

    $valeur = $this->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => 'on'])
        ->getCookie($recaller)
        ->getValue();

    $this->flushSession();
    $this->app['auth']->forgetGuards();

    $this->withCookie($recaller, $valeur)->get(route('dashboard'))->assertOk();
    $this->assertAuthenticatedAs($user);
});

function changerSonMotDePasse($test)
{
    return $test->put('/password', [
        'current_password' => 'password',
        'password' => 'Nouveau-Pass1',
        'password_confirmation' => 'Nouveau-Pass1',
    ]);
}

it('re-issues its own remember-me cookie after a password change', function () {
    $user = User::factory()->create();
    $recaller = Auth::guard()->getRecallerName();

    $this->actingAs($user)->withCookie($recaller, $user->id.'|'.$user->remember_token.'|'.$user->password);

    changerSonMotDePasse($this)->assertSessionHasNoErrors()->assertCookie($recaller);
});

it('does not create a remember-me cookie after a password change when there was none', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    changerSonMotDePasse($this)->assertSessionHasNoErrors()->assertCookieMissing(Auth::guard()->getRecallerName());
});

it('does not turn a foreign or malformed remember-me cookie into one for the user', function (string $valeur) {
    $user = User::factory()->create();
    $autre = User::factory()->create();
    $recaller = Auth::guard()->getRecallerName();

    $this->actingAs($user)->withCookie($recaller, str_replace('{autre}', $autre->id.'|'.$autre->remember_token.'|'.$autre->password, $valeur));

    changerSonMotDePasse($this)->assertSessionHasNoErrors()->assertCookieMissing($recaller);
})->with([
    'cookie d\'un autre compte' => '{autre}',
    'cookie illisible' => 'n-importe-quoi',
]);

it('forgets the cookie on logout', function () {
    // Laravel n'expire le cookie que si la requête de déconnexion le porte : on se connecte donc vraiment.
    $user = User::factory()->create();
    $recaller = Auth::guard()->getRecallerName();

    $valeur = $this->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => 'on'])
        ->getCookie($recaller)
        ->getValue();

    $this->withCookie($recaller, $valeur)->post(route('logout'))->assertCookieExpired($recaller);
});
