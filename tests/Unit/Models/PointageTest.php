<?php

use App\Enums\SourcePointage;
use App\Enums\StatutPonctualite;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use Illuminate\Database\QueryException;

it('casts statut_ponctualite and source to their enums', function () {
    $pointage = Pointage::factory()->create([
        'statut_ponctualite' => 'en_retard',
        'source' => 'auto',
    ]);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::EnRetard);
    expect($pointage->source)->toBe(SourcePointage::Auto);
});

it('resolves the polymorphic pointable to a fille', function () {
    $fille = Fille::factory()->create();
    $pointage = Pointage::factory()->create([
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
    ]);

    expect($pointage->pointable->is($fille))->toBeTrue();
});

it('belongs to a séance', function () {
    $seance = Seance::factory()->create();
    $pointage = Pointage::factory()->create(['seance_id' => $seance->id]);

    expect($pointage->seance->is($seance))->toBeTrue();
});

it('rejects a second pointage for the same person at the same séance', function () {
    $seance = Seance::factory()->create();
    $fille = Fille::factory()->create();

    Pointage::factory()->create([
        'seance_id' => $seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
    ]);

    Pointage::factory()->create([
        'seance_id' => $seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
    ]);
})->throws(QueryException::class);
