<?php

use App\Enums\JourSemaine;
use App\Models\PlanningRepetition;

it('casts jour_semaine to the JourSemaine enum', function () {
    $planning = PlanningRepetition::factory()->create(['jour_semaine' => 2]);

    expect($planning->jour_semaine)->toBe(JourSemaine::Mardi);
});

it('belongs to a referent coach', function () {
    $planning = PlanningRepetition::factory()->create();

    expect($planning->coach)->not->toBeNull();
});

it('defaults to actif', function () {
    $planning = PlanningRepetition::factory()->create();

    expect($planning->actif)->toBeTrue();
});
