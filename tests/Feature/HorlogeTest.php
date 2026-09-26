<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(fn () => Carbon::setTestNow('2026-09-26 12:27:48'));
afterEach(fn () => Carbon::setTestNow());

it('shows the server date and time under the logo on the login screen', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('samedi 26 septembre 2026 · 12:27:48')
        ->assertSee('data-horloge', false);
});

it('shows it under the logo in the sidebar', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('samedi 26 septembre 2026 · 12:27:48');
});

it('shows it in the kiosk top bar', function () {
    $this->get(route('kiosque.home'))->assertOk()->assertSee('samedi 26 septembre 2026 · 12:27:48');
});

it('renders the CAFAB logo once on the login card', function () {
    // Deux occurrences attendues : l'icône de l'onglet et le logo de la carte.
    expect(substr_count($this->get(route('login'))->getContent(), 'images/logo-cafab.png'))->toBe(2);
});
