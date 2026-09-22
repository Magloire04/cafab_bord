<?php

use App\Enums\StatutSeance;
use App\Models\Seance;
use Illuminate\Support\Carbon;

it('starts a séance once its heure_prevue has arrived', function () {
    Carbon::setTestNow('2026-09-22 17:00:30');

    $seance = Seance::factory()->create([
        'date' => '2026-09-22',
        'heure_prevue' => '17:00:00',
        'statut' => StatutSeance::AVenir,
    ]);

    $this->artisan('seances:demarrer');

    expect($seance->fresh()->statut)->toBe(StatutSeance::EnCours);

    Carbon::setTestNow();
});

it('leaves a séance a_venir if its heure_prevue has not arrived yet', function () {
    Carbon::setTestNow('2026-09-22 16:00:00');

    $seance = Seance::factory()->create([
        'date' => '2026-09-22',
        'heure_prevue' => '17:00:00',
        'statut' => StatutSeance::AVenir,
    ]);

    $this->artisan('seances:demarrer');

    expect($seance->fresh()->statut)->toBe(StatutSeance::AVenir);

    Carbon::setTestNow();
});
