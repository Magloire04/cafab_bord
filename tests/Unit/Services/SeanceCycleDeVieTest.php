<?php

use App\Enums\StatutSeance;
use App\Models\PlanningRepetition;
use App\Models\Seance;
use App\Services\SeanceCycleDeVie;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

it('generates, starts and closes séances in one pass', function () {
    Carbon::setTestNow('2026-09-22 17:05:00'); // mardi

    $jeudi = PlanningRepetition::factory()->create(['jour_semaine' => 4, 'heure_debut' => '17:00:00', 'actif' => true]);
    $aDemarrer = Seance::factory()->create(['date' => '2026-09-22', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);
    $oubliee = Seance::factory()->create(['date' => '2026-09-21', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::EnCours]);

    app(SeanceCycleDeVie::class)->synchroniser();

    expect(Seance::where('planning_repetition_id', $jeudi->id)->whereDate('date', '2026-09-24')->exists())->toBeTrue();
    expect($aDemarrer->fresh()->statut)->toBe(StatutSeance::EnCours);
    expect($oubliee->fresh()->statut)->toBe(StatutSeance::Cloturee);
});

it('does not start a séance of today before its hour', function () {
    Carbon::setTestNow('2026-09-22 16:59:00');
    $seance = Seance::factory()->create(['date' => '2026-09-22', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    expect(app(SeanceCycleDeVie::class)->demarrer())->toBe(0);
    expect($seance->fresh()->statut)->toBe(StatutSeance::AVenir);
});
