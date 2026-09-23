<?php

use App\Enums\StatutCachet;
use App\Enums\UserRole;
use App\Exceptions\CachetException;
use App\Models\Cachet;
use App\Models\Prestation;
use App\Models\User;
use App\Services\CachetService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-23 10:00:00');
    $this->service = new CachetService;
});

afterEach(function () {
    Carbon::setTestNow();
});

it('validates a cachet, recording who and when', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::DeclareePayee]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $result = $this->service->valider($cachet, $admin);

    expect($result->statut)->toBe(StatutCachet::ValideePayee);
    expect($result->valide_par_user_id)->toBe($admin->id);
    expect($result->validee_at)->not->toBeNull();
});

it('rejects validating an already validated cachet', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::ValideePayee]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->service->valider($cachet, $admin);
})->throws(CachetException::class);

it('rejects validating an annule cachet', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::Annule]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->service->valider($cachet, $admin);
})->throws(CachetException::class);

it('corrects a cachet declaration with a motif, recording who', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::DeclareeNonPayee]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $result = $this->service->corriger($cachet, StatutCachet::DeclareePayee, 'La fille a confirmé oralement avoir reçu son cachet.', $admin);

    expect($result->statut)->toBe(StatutCachet::DeclareePayee);
    expect($result->corrige_par_user_id)->toBe($admin->id);
    expect($result->motif_correction)->toBe('La fille a confirmé oralement avoir reçu son cachet.');
});

it('rejects correcting an already validated cachet', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::ValideePayee]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->service->corriger($cachet, StatutCachet::DeclareePayee, 'Motif quelconque.', $admin);
})->throws(CachetException::class);

it('adjusts a cachet montant before validation', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::Du, 'montant' => 5000]);

    $result = $this->service->ajusterMontant($cachet, 7500);

    expect($result->montant)->toBe('7500.00');
});

it('rejects adjusting the montant of an already validated cachet', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::ValideePayee]);

    $this->service->ajusterMontant($cachet, 7500);
})->throws(CachetException::class);
