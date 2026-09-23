<?php

use App\Enums\StatutCachet;
use App\Enums\UserRole;
use App\Models\Cachet;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from validating a cachet', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::DeclareePayee]);

    $this->actingAs($coach)->patch(route('admin.cachets.valider', $cachet))->assertForbidden();
});

it('lets the admin validate a cachet', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::DeclareePayee]);

    $this->actingAs($this->admin)->patch(route('admin.cachets.valider', $cachet))
        ->assertRedirect(route('admin.prestations.show', $cachet->prestation_id));

    expect($cachet->fresh()->statut)->toBe(StatutCachet::ValideePayee);
});

it('shows an error and does not change statut when validating an already validated cachet', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::ValideePayee]);

    $this->actingAs($this->admin)->patch(route('admin.cachets.valider', $cachet))
        ->assertRedirect(route('admin.prestations.show', $cachet->prestation_id))
        ->assertSessionHas('error');

    expect($cachet->fresh()->statut)->toBe(StatutCachet::ValideePayee);
});

it('lets the admin correct a declaration with a motif', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::DeclareeNonPayee]);

    $this->actingAs($this->admin)->patch(route('admin.cachets.corriger', $cachet), [
        'statut' => 'declaree_payee',
        'motif' => 'La fille a confirmé oralement avoir reçu son cachet.',
    ])->assertRedirect(route('admin.prestations.show', $cachet->prestation_id));

    expect($cachet->fresh()->statut)->toBe(StatutCachet::DeclareePayee);
});

it('requires a motif to correct a declaration', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::DeclareeNonPayee]);

    $this->actingAs($this->admin)->patch(route('admin.cachets.corriger', $cachet), [
        'statut' => 'declaree_payee',
    ])->assertSessionHasErrors('motif');
});

it('lets the admin adjust the montant before validation', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::Du, 'montant' => 5000]);

    $this->actingAs($this->admin)->patch(route('admin.cachets.ajuster-montant', $cachet), ['montant' => 7500])
        ->assertRedirect(route('admin.prestations.show', $cachet->prestation_id));

    expect($cachet->fresh()->montant)->toBe('7500.00');
});
