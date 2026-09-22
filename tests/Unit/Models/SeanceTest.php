<?php

use App\Enums\StatutSeance;
use App\Enums\TypeSeance;
use App\Models\Seance;

it('casts type and statut to their enums', function () {
    $seance = Seance::factory()->create(['type' => 'recurrente', 'statut' => 'a_venir']);

    expect($seance->type)->toBe(TypeSeance::Recurrente);
    expect($seance->statut)->toBe(StatutSeance::AVenir);
});

it('combines date and heure_prevue into one Carbon instant', function () {
    $seance = Seance::factory()->create(['date' => '2026-09-22', 'heure_prevue' => '17:00:00']);

    $instant = $seance->heurePrevueCarbon();

    expect($instant->format('Y-m-d H:i:s'))->toBe('2026-09-22 17:00:00');
});

it('reports en cours only when statut is en_cours', function () {
    $seance = Seance::factory()->create(['statut' => 'en_cours']);

    expect($seance->estEnCours())->toBeTrue();

    $seance->statut = StatutSeance::Cloturee;
    expect($seance->estEnCours())->toBeFalse();
});
