<?php

use App\Enums\StatutCachet;
use App\Exceptions\CachetException;
use App\Models\Cachet;
use App\Models\Prestation;
use App\Services\CachetService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-23 10:00:00');
    $this->service = new CachetService;
});

afterEach(function () {
    Carbon::setTestNow();
});

it('marks a cachet declaree_payee when the fille declares having received it', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::Du]);

    $result = $this->service->declarer($cachet, true);

    expect($result->statut)->toBe(StatutCachet::DeclareePayee);
    expect($result->declaree_at)->not->toBeNull();
});

it('marks a cachet declaree_non_payee when the fille declares not having received it', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::Du]);

    $result = $this->service->declarer($cachet, false);

    expect($result->statut)->toBe(StatutCachet::DeclareeNonPayee);
});

it('lets the fille change her mind before validation', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::DeclareeNonPayee]);

    $result = $this->service->declarer($cachet, true);

    expect($result->statut)->toBe(StatutCachet::DeclareePayee);
});

it('rejects a declaration for a prestation that has not happened yet', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-23']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::Du]);

    $this->service->declarer($cachet, true);
})->throws(CachetException::class);

it('rejects a declaration for an already validated cachet', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::ValideePayee]);

    $this->service->declarer($cachet, true);
})->throws(CachetException::class);

it('rejects a declaration for a cachet whose prestation was cancelled', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::Annule]);

    $this->service->declarer($cachet, true);
})->throws(CachetException::class);
