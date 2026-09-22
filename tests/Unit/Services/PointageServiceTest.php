<?php

use App\Enums\SourcePointage;
use App\Enums\StatutPonctualite;
use App\Enums\StatutSeance;
use App\Exceptions\PointageException;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use App\Services\PointageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seance = Seance::factory()->create([
        'date' => '2026-09-22',
        'heure_prevue' => '17:00:00',
        'statut' => StatutSeance::EnCours,
    ]);
    $this->fille = Fille::factory()->create();
    $this->service = new PointageService;
});

it('marks a pointage à l’heure when arriving before or at the scheduled time', function () {
    $pointage = $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 16:58:00'), SourcePointage::Auto);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::ALHeure);
    expect($pointage->minutes_retard)->toBe(0);
});

it('marks en retard between 1 and 15 minutes late', function () {
    $pointage = $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 17:10:00'), SourcePointage::Auto);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::EnRetard);
    expect($pointage->minutes_retard)->toBe(10);
});

it('marks retard fort beyond 15 minutes late', function () {
    $pointage = $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 17:20:00'), SourcePointage::Auto);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::RetardFort);
    expect($pointage->minutes_retard)->toBe(20);
});

it('treats exactly 15 minutes late as en retard, not retard fort', function () {
    $pointage = $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 17:15:00'), SourcePointage::Auto);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::EnRetard);
    expect($pointage->minutes_retard)->toBe(15);
});

it('treats exactly 16 minutes late as retard fort', function () {
    $pointage = $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 17:16:00'), SourcePointage::Auto);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::RetardFort);
    expect($pointage->minutes_retard)->toBe(16);
});

it('treats an exact on-time arrival as à l’heure with zero minutes late', function () {
    $pointage = $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 17:00:00'), SourcePointage::Auto);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::ALHeure);
    expect($pointage->minutes_retard)->toBe(0);
});

it('rejects a pointage for a séance that is not en cours', function () {
    $this->seance->update(['statut' => StatutSeance::AVenir]);

    $this->service->pointer($this->seance, $this->fille, Carbon::now(), SourcePointage::Auto);
})->throws(PointageException::class);

it('rejects a second pointage for the same person — first one wins', function () {
    $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 16:58:00'), SourcePointage::Auto);

    $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 17:30:00'), SourcePointage::Coach);
})->throws(PointageException::class);

it('lets a coach record a pointage at a specific backdated time, not just now', function () {
    $pointage = $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 17:02:00'), SourcePointage::Coach);

    expect($pointage->source)->toBe(SourcePointage::Coach);
    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::EnRetard);
});

it('converts a duplicate-insert race caught by the DB unique constraint into PointageException, not a raw QueryException', function () {
    // Simulate two requests landing at nearly the same instant: both would
    // pass the exists() pre-check before either commits. We can't trigger
    // that with real concurrency in a synchronous test, so we bypass the
    // pre-check directly (via a subclass that always reports "not yet
    // pointed") and pre-seed the colliding row so the insert itself is the
    // one that fails — exactly what the DB unique constraint does on a real
    // race. The point of this test is the catch branch in pointer(): it
    // must turn that QueryException into PointageException::dejaPointe(),
    // not let it escape raw.
    $serviceQuiIgnoreLePreCheck = new class extends PointageService
    {
        protected function existeDejaPointage(Seance $seance, Model $personne): bool
        {
            return false;
        }
    };

    Pointage::factory()->create([
        'seance_id' => $this->seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $this->fille->id,
    ]);

    $serviceQuiIgnoreLePreCheck->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 17:02:00'), SourcePointage::Coach);
})->throws(PointageException::class);
