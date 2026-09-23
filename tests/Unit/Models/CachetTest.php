<?php

use App\Enums\StatutCachet;
use App\Models\Cachet;
use Illuminate\Database\QueryException;

it('casts statut to the StatutCachet enum and defaults to du', function () {
    $cachet = Cachet::factory()->create();

    expect($cachet->statut)->toBe(StatutCachet::Du);
});

it('belongs to a prestation and a fille', function () {
    $cachet = Cachet::factory()->create();

    expect($cachet->prestation)->not->toBeNull();
    expect($cachet->fille)->not->toBeNull();
});

it('rejects a second cachet for the same fille on the same prestation', function () {
    $cachet = Cachet::factory()->create();

    Cachet::factory()->create([
        'prestation_id' => $cachet->prestation_id,
        'fille_id' => $cachet->fille_id,
    ]);
})->throws(QueryException::class);

it('reports estFinalise only for validee_payee or annule', function () {
    $du = Cachet::factory()->create(['statut' => StatutCachet::Du]);
    $declaree = Cachet::factory()->create(['statut' => StatutCachet::DeclareePayee]);
    $validee = Cachet::factory()->create(['statut' => StatutCachet::ValideePayee]);
    $annule = Cachet::factory()->create(['statut' => StatutCachet::Annule]);

    expect($du->estFinalise())->toBeFalse();
    expect($declaree->estFinalise())->toBeFalse();
    expect($validee->estFinalise())->toBeTrue();
    expect($annule->estFinalise())->toBeTrue();
});
