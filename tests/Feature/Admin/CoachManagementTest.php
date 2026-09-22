<?php

use App\Enums\StatutPersonne;
use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from the registre', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.coaches.index'))->assertForbidden();
});

it('lets the admin list coaches', function () {
    Coach::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.coaches.index'))
        ->assertOk();
});

it('lets the admin create a coach with a generated pin', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.coaches.store'), [
        'name' => 'Prudence Aïvodji',
        'email' => 'prudence@cafab.bj',
        'contact' => '+229 01 00 00 00 00',
        'date_entree' => '2026-01-15',
    ]);

    $response->assertRedirect(route('admin.coaches.index'));

    $user = User::where('email', 'prudence@cafab.bj')->firstOrFail();
    expect($user->role)->toBe(UserRole::Coach);

    $coach = Coach::where('user_id', $user->id)->firstOrFail();
    expect($coach->pin)->toMatch('/^\d{4}$/');
    expect($coach->statut)->toBe(StatutPersonne::Actif);
});

it('lets the admin update a coach contact', function () {
    $coach = Coach::factory()->create(['contact' => '+229 00 00 00 00 00']);

    $this->actingAs($this->admin)
        ->put(route('admin.coaches.update', $coach), [
            'contact' => '+229 11 11 11 11 11',
            'date_entree' => $coach->date_entree->format('Y-m-d'),
        ])
        ->assertRedirect(route('admin.coaches.index'));

    expect($coach->fresh()->contact)->toBe('+229 11 11 11 11 11');
});

it('lets the admin deactivate then reactivate a coach', function () {
    $coach = Coach::factory()->create(['statut' => 'actif']);

    $this->actingAs($this->admin)->patch(route('admin.coaches.toggle-statut', $coach));
    expect($coach->fresh()->statut)->toBe(StatutPersonne::Inactif);

    $this->actingAs($this->admin)->patch(route('admin.coaches.toggle-statut', $coach));
    expect($coach->fresh()->statut)->toBe(StatutPersonne::Actif);
});

it('lets the admin regenerate a coach pin', function () {
    $coach = Coach::factory()->create(['pin' => '9999']);

    $this->actingAs($this->admin)->patch(route('admin.coaches.regenerate-pin', $coach));

    expect($coach->fresh()->pin)->not->toBe('9999');
});
