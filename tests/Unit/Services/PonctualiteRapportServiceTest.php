<?php

use App\Enums\SourcePointage;
use App\Enums\StatutPonctualite;
use App\Models\Coach;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use App\Services\PonctualiteRapportService;

beforeEach(function () {
    $this->service = new PonctualiteRapportService;
});

it('aggregates presences, retards, retard cumule and absences per fille', function () {
    $fille = Fille::factory()->create(['prenom' => 'Awa', 'nom' => 'Dupont']);
    $seance1 = Seance::factory()->create(['date' => '2026-09-15']);
    $seance2 = Seance::factory()->create(['date' => '2026-09-16']);
    $seance3 = Seance::factory()->create(['date' => '2026-09-17']);

    Pointage::factory()->create([
        'seance_id' => $seance1->id, 'pointable_type' => Fille::class, 'pointable_id' => $fille->id,
        'statut_ponctualite' => StatutPonctualite::ALHeure, 'minutes_retard' => 0, 'source' => SourcePointage::Auto,
    ]);
    Pointage::factory()->create([
        'seance_id' => $seance2->id, 'pointable_type' => Fille::class, 'pointable_id' => $fille->id,
        'statut_ponctualite' => StatutPonctualite::EnRetard, 'minutes_retard' => 10, 'source' => SourcePointage::Auto,
    ]);
    Pointage::factory()->create([
        'seance_id' => $seance3->id, 'pointable_type' => Fille::class, 'pointable_id' => $fille->id,
        'statut_ponctualite' => StatutPonctualite::Absent, 'minutes_retard' => null, 'source' => SourcePointage::Coach,
    ]);

    $resultat = $this->service->generer(null, null, null, null);

    expect($resultat)->toHaveCount(1);
    $ligne = $resultat->first();
    expect($ligne['type'])->toBe('Fille');
    expect($ligne['nom'])->toBe('Awa Dupont');
    expect($ligne['presences'])->toBe(1);
    expect($ligne['retards'])->toBe(1);
    expect($ligne['retard_cumule'])->toBe(10);
    expect($ligne['absences'])->toBe(1);
    expect($ligne['taux_presence'])->toBe(66.7);
});

it('reports a coach by their user name', function () {
    $coach = Coach::factory()->create();
    $seance = Seance::factory()->create(['date' => '2026-09-15']);

    Pointage::factory()->create([
        'seance_id' => $seance->id, 'pointable_type' => Coach::class, 'pointable_id' => $coach->id,
        'statut_ponctualite' => StatutPonctualite::ALHeure, 'minutes_retard' => 0,
    ]);

    $resultat = $this->service->generer(null, null, null, null);

    expect($resultat->first()['type'])->toBe('Coach');
    expect($resultat->first()['nom'])->toBe($coach->user->name);
});

it('filters by period using the seance date', function () {
    $fille = Fille::factory()->create();
    $dedans = Seance::factory()->create(['date' => '2026-09-15']);
    $dehors = Seance::factory()->create(['date' => '2026-01-01']);

    Pointage::factory()->create(['seance_id' => $dedans->id, 'pointable_type' => Fille::class, 'pointable_id' => $fille->id, 'statut_ponctualite' => StatutPonctualite::ALHeure]);
    Pointage::factory()->create(['seance_id' => $dehors->id, 'pointable_type' => Fille::class, 'pointable_id' => $fille->id, 'statut_ponctualite' => StatutPonctualite::ALHeure]);

    $resultat = $this->service->generer('2026-09-01', '2026-09-30', null, null);

    expect($resultat->first()['presences'])->toBe(1);
});

it('filters to a single fille', function () {
    $fille1 = Fille::factory()->create();
    $fille2 = Fille::factory()->create();
    $seance = Seance::factory()->create(['date' => '2026-09-15']);

    Pointage::factory()->create(['seance_id' => $seance->id, 'pointable_type' => Fille::class, 'pointable_id' => $fille1->id, 'statut_ponctualite' => StatutPonctualite::ALHeure]);
    Pointage::factory()->create(['seance_id' => $seance->id, 'pointable_type' => Fille::class, 'pointable_id' => $fille2->id, 'statut_ponctualite' => StatutPonctualite::ALHeure]);

    $resultat = $this->service->generer(null, null, $fille1->id, null);

    expect($resultat)->toHaveCount(1);
});

it('returns an empty collection when nothing matches', function () {
    expect($this->service->generer(null, null, null, null))->toHaveCount(0);
});
