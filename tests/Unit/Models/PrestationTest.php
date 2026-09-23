<?php

use App\Enums\StatutPrestation;
use App\Models\Cachet;
use App\Models\Prestation;
use Illuminate\Support\Carbon;

it('casts statut to the StatutPrestation enum and defaults to active', function () {
    $prestation = Prestation::factory()->create();

    expect($prestation->statut)->toBe(StatutPrestation::Active);
});

it('has many cachets', function () {
    $prestation = Prestation::factory()->create();
    Cachet::factory()->count(2)->create(['prestation_id' => $prestation->id]);

    expect($prestation->cachets)->toHaveCount(2);
});

it('reports estPassee only once its date is strictly before today', function () {
    Carbon::setTestNow('2026-09-23 10:00:00');

    $hier = Prestation::factory()->create(['date' => '2026-09-22']);
    $aujourdhui = Prestation::factory()->create(['date' => '2026-09-23']);

    expect($hier->estPassee())->toBeTrue();
    expect($aujourdhui->estPassee())->toBeFalse();

    Carbon::setTestNow();
});
