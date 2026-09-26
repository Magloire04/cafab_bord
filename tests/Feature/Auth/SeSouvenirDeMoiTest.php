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

it('forgets the cookie on logout', function () {
    // Laravel n'expire le cookie que si la requête de déconnexion le porte : on se connecte donc vraiment.
    $user = User::factory()->create();
    $recaller = Auth::guard()->getRecallerName();

    $valeur = $this->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => 'on'])
        ->getCookie($recaller)
        ->getValue();

    $this->withCookie($recaller, $valeur)->post(route('logout'))->assertCookieExpired($recaller);
});
