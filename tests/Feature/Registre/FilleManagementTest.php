<?php

use App\Enums\StatutPersonne;
use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\Fille;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('lets the admin list filles', function () {
    Fille::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('filles.index'))
        ->assertOk();
});

it('lets the admin create a fille with a generated pin', function () {
    $response = $this->actingAs($this->admin)->post(route('filles.store'), [
        'nom' => 'Hounkpatin',
        'prenom' => 'Sènami',
        'contact' => '+229 01 00 00 00 00',
        'date_entree' => '2026-01-15',
    ]);

    $response->assertRedirect(route('filles.index'));

    $fille = Fille::where('nom', 'Hounkpatin')->where('prenom', 'Sènami')->firstOrFail();
    expect($fille->pin)->toMatch('/^\d{4}$/');
    expect($fille->statut)->toBe(StatutPersonne::Actif);
});

it('lets the admin update a fille contact', function () {
    $fille = Fille::factory()->create(['contact' => '+229 00 00 00 00 00']);

    $this->actingAs($this->admin)
        ->put(route('filles.update', $fille), [
            'nom' => $fille->nom,
            'prenom' => $fille->prenom,
            'contact' => '+229 11 11 11 11 11',
            'date_entree' => $fille->date_entree->format('Y-m-d'),
        ])
        ->assertRedirect(route('filles.index'));

    expect($fille->fresh()->contact)->toBe('+229 11 11 11 11 11');
});

it('lets the admin deactivate then reactivate a fille', function () {
    $fille = Fille::factory()->create(['statut' => 'actif']);

    $this->actingAs($this->admin)->patch(route('filles.toggle-statut', $fille));
    expect($fille->fresh()->statut)->toBe(StatutPersonne::Inactif);

    $this->actingAs($this->admin)->patch(route('filles.toggle-statut', $fille));
    expect($fille->fresh()->statut)->toBe(StatutPersonne::Actif);
});

it('lets the admin regenerate a fille pin', function () {
    $fille = Fille::factory()->create(['pin' => '9999']);

    $this->actingAs($this->admin)->patch(route('filles.regenerate-pin', $fille));

    expect($fille->fresh()->pin)->not->toBe('9999');
});

it('lets a coach list the filles', function () {
    $coach = Coach::factory()->create();
    Fille::factory()->count(2)->create();

    $this->actingAs($coach->user)->get(route('filles.index'))->assertOk();
});

it('lets a coach add a fille', function () {
    $coach = Coach::factory()->create();

    $this->actingAs($coach->user)->post(route('filles.store'), [
        'nom' => 'Adjovi',
        'prenom' => 'Chimène',
        'date_entree' => '2026-09-01',
    ])->assertRedirect(route('filles.index'));

    expect(Fille::where('nom', 'Adjovi')->exists())->toBeTrue();
});

it('lets a coach update a fille and regenerate her pin', function () {
    $coach = Coach::factory()->create();
    $fille = Fille::factory()->create(['pin' => '9999']);

    $this->actingAs($coach->user)->put(route('filles.update', $fille), [
        'nom' => $fille->nom,
        'prenom' => 'Grâce',
        'date_entree' => $fille->date_entree->format('Y-m-d'),
    ])->assertRedirect(route('filles.index'));
    $this->actingAs($coach->user)->patch(route('filles.regenerate-pin', $fille));

    expect($fille->fresh()->prenom)->toBe('Grâce');
    expect($fille->fresh()->pin)->not->toBe('9999');
});

it('refuses to let a coach deactivate a fille', function () {
    $coach = Coach::factory()->create();
    $fille = Fille::factory()->create(['statut' => 'actif']);

    $this->actingAs($coach->user)->patch(route('filles.toggle-statut', $fille))->assertForbidden();

    expect($fille->fresh()->statut)->toBe(StatutPersonne::Actif);
});

it('offers the admin the deactivation in the row menu', function () {
    Fille::factory()->create(['statut' => 'actif']);

    $this->actingAs($this->admin)->get(route('filles.index'))
        ->assertSee('Désactiver')
        ->assertSee('Régénérer le PIN');
});

it('does not offer the deactivation to a coach', function () {
    $coach = Coach::factory()->create();
    Fille::factory()->create(['statut' => 'actif']);

    $this->actingAs($coach->user)->get(route('filles.index'))->assertDontSee('Désactiver');
});
