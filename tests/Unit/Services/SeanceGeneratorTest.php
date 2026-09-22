<?php

use App\Enums\TypeSeance;
use App\Models\PlanningRepetition;
use App\Models\Seance;
use App\Services\SeanceGenerator;
use Illuminate\Support\Carbon;

it('generates a séance for each active planning occurring in the window', function () {
    Carbon::setTestNow('2026-09-22 08:00:00'); // a Tuesday

    $mardi = PlanningRepetition::factory()->create(['jour_semaine' => 2, 'heure_debut' => '17:00:00', 'actif' => true]);
    PlanningRepetition::factory()->create(['jour_semaine' => 2, 'heure_debut' => '17:00:00', 'actif' => false]);

    $created = (new SeanceGenerator)->genererPourLesProchainsJours(14);

    expect($created)->toBeGreaterThan(0);
    expect(Seance::where('planning_repetition_id', $mardi->id)->where('type', TypeSeance::Recurrente)->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('is idempotent — running it twice does not duplicate séances', function () {
    Carbon::setTestNow('2026-09-22 08:00:00');

    PlanningRepetition::factory()->create(['jour_semaine' => 2, 'heure_debut' => '17:00:00', 'actif' => true]);

    $generator = new SeanceGenerator;
    $first = $generator->genererPourLesProchainsJours(14);
    $second = $generator->genererPourLesProchainsJours(14);

    expect($second)->toBe(0);
    expect(Seance::count())->toBe($first);

    Carbon::setTestNow();
});
