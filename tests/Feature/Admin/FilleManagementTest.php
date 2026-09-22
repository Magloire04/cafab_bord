<?php

use App\Enums\StatutPersonne;
use App\Enums\UserRole;
use App\Models\Fille;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from the registre des filles', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.filles.index'))->assertForbidden();
});

it('lets the admin list filles', function () {
    Fille::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.filles.index'))
        ->assertOk();
});

it('lets the admin create a fille with a generated pin', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.filles.store'), [
        'nom' => 'Hounkpatin',
        'prenom' => 'Sènami',
        'contact' => '+229 01 00 00 00 00',
        'date_entree' => '2026-01-15',
    ]);

    $response->assertRedirect(route('admin.filles.index'));

    $fille = Fille::where('nom', 'Hounkpatin')->where('prenom', 'Sènami')->firstOrFail();
    expect($fille->pin)->toMatch('/^\d{4}$/');
    expect($fille->statut)->toBe(StatutPersonne::Actif);
});

it('lets the admin update a fille contact', function () {
    $fille = Fille::factory()->create(['contact' => '+229 00 00 00 00 00']);

    $this->actingAs($this->admin)
        ->put(route('admin.filles.update', $fille), [
            'nom' => $fille->nom,
            'prenom' => $fille->prenom,
            'contact' => '+229 11 11 11 11 11',
            'date_entree' => $fille->date_entree->format('Y-m-d'),
        ])
        ->assertRedirect(route('admin.filles.index'));

    expect($fille->fresh()->contact)->toBe('+229 11 11 11 11 11');
});

it('lets the admin deactivate then reactivate a fille', function () {
    $fille = Fille::factory()->create(['statut' => 'actif']);

    $this->actingAs($this->admin)->patch(route('admin.filles.toggle-statut', $fille));
    expect($fille->fresh()->statut)->toBe(StatutPersonne::Inactif);

    $this->actingAs($this->admin)->patch(route('admin.filles.toggle-statut', $fille));
    expect($fille->fresh()->statut)->toBe(StatutPersonne::Actif);
});

it('lets the admin regenerate a fille pin', function () {
    $fille = Fille::factory()->create(['pin' => '9999']);

    $this->actingAs($this->admin)->patch(route('admin.filles.regenerate-pin', $fille));

    expect($fille->fresh()->pin)->not->toBe('9999');
});
