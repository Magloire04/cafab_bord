<?php

use App\Enums\StatutPersonne;
use App\Models\Fille;
use Illuminate\Database\QueryException;

it('casts statut to the StatutPersonne enum and defaults to actif', function () {
    $fille = Fille::factory()->create();

    expect($fille->statut)->toBe(StatutPersonne::Actif);
});

it('rejects a duplicate pin', function () {
    Fille::factory()->create(['pin' => '4321']);

    Fille::factory()->create(['pin' => '4321']);
})->throws(QueryException::class);

it('exposes nom and prenom', function () {
    $fille = Fille::factory()->create(['nom' => 'Hounkpatin', 'prenom' => 'Sènami']);

    expect($fille->nom)->toBe('Hounkpatin');
    expect($fille->prenom)->toBe('Sènami');
});
