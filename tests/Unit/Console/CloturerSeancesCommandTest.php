<?php

use App\Enums\StatutSeance;
use App\Models\Seance;
use Illuminate\Support\Carbon;

it('clôture automatiquement une séance en_cours dont la date est passée', function () {
    Carbon::setTestNow('2026-09-22 10:00:00');

    $seance = Seance::factory()->create([
        'date' => '2026-09-21',
        'heure_prevue' => '17:00:00',
        'statut' => StatutSeance::EnCours,
    ]);

    $this->artisan('seances:cloturer');

    expect($seance->fresh()->statut)->toBe(StatutSeance::Cloturee);
    expect($seance->fresh()->cloturee_at)->not->toBeNull();
    expect($seance->fresh()->cloture_par_user_id)->toBeNull();

    Carbon::setTestNow();
});

it('leaves a séance en_cours dated today alone', function () {
    Carbon::setTestNow('2026-09-22 20:00:00');

    $seance = Seance::factory()->create([
        'date' => '2026-09-22',
        'heure_prevue' => '17:00:00',
        'statut' => StatutSeance::EnCours,
    ]);

    $this->artisan('seances:cloturer');

    expect($seance->fresh()->statut)->toBe(StatutSeance::EnCours);

    Carbon::setTestNow();
});

it('clôture automatiquement une séance a_venir dont la date est passée et qui n\'a jamais démarré', function () {
    Carbon::setTestNow('2026-09-22 10:00:00');

    $seance = Seance::factory()->create([
        'date' => '2026-09-21',
        'heure_prevue' => '17:00:00',
        'statut' => StatutSeance::AVenir,
    ]);

    $this->artisan('seances:cloturer');

    expect($seance->fresh()->statut)->toBe(StatutSeance::Cloturee);
    expect($seance->fresh()->cloturee_at)->not->toBeNull();

    Carbon::setTestNow();
});

it('leaves a séance a_venir dated today alone', function () {
    Carbon::setTestNow('2026-09-22 10:00:00');

    $seance = Seance::factory()->create([
        'date' => '2026-09-22',
        'heure_prevue' => '17:00:00',
        'statut' => StatutSeance::AVenir,
    ]);

    $this->artisan('seances:cloturer');

    expect($seance->fresh()->statut)->toBe(StatutSeance::AVenir);

    Carbon::setTestNow();
});
