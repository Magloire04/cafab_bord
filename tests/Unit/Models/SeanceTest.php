<?php

use App\Enums\StatutPonctualite;
use App\Enums\StatutSeance;
use App\Enums\TypeSeance;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use App\Models\User;

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

it('normalizes heure_prevue to H:i:s on write', function () {
    $seance = Seance::factory()->create(['heure_prevue' => '18:00']);

    expect($seance->heure_prevue)->toBe('18:00:00');
});

it('clôture the séance and materializes absent pointages for filles without one', function () {
    $seance = Seance::factory()->create(['statut' => StatutSeance::EnCours]);
    $user = User::factory()->create();

    $filles = Fille::factory()->count(3)->create();
    Pointage::factory()->create([
        'seance_id' => $seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $filles[0]->id,
    ]);
    Pointage::factory()->create([
        'seance_id' => $seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $filles[1]->id,
    ]);

    $seance->clore($user);

    $seance->refresh();
    expect($seance->statut)->toBe(StatutSeance::Cloturee);
    expect($seance->cloturee_at)->not->toBeNull();
    expect($seance->cloture_par_user_id)->toBe($user->id);

    expect($seance->pointages()->count())->toBe(3);

    $absent = Pointage::where('seance_id', $seance->id)
        ->where('pointable_id', $filles[2]->id)
        ->first();

    expect($absent)->not->toBeNull();
    expect($absent->statut_ponctualite)->toBe(StatutPonctualite::Absent);
    expect($absent->pointe_a)->toBeNull();
    expect($absent->minutes_retard)->toBeNull();
});
