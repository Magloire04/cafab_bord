<?php

use App\Enums\StatutCachet;
use App\Enums\UserRole;
use App\Exceptions\CachetException;
use App\Exceptions\CaisseCafabException;
use App\Models\Cachet;
use App\Models\Prestation;
use App\Models\User;
use App\Services\CachetService;
use App\Services\CaisseCafabClient;
use Mockery;

it('marks the depense as created when Caisse CAFAB accepts it during validation', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['id' => 1, 'prestation_id' => $prestation->id, 'statut' => StatutCachet::DeclareePayee]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $client = Mockery::mock(CaisseCafabClient::class);
    $client->shouldReceive('creerDepense')->once()->with(Mockery::on(fn ($c) => $c->id === $cachet->id))->andReturn('cachet-1');

    $result = (new CachetService($client))->valider($cachet, $admin);

    expect($result->depense_creee_at)->not->toBeNull();
    expect($result->caisse_cafab_reference)->toBe('cachet-1');
    expect($result->depense_erreur)->toBeNull();
});

it('still validates locally, recording the error, when Caisse CAFAB call fails', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['id' => 1, 'prestation_id' => $prestation->id, 'statut' => StatutCachet::DeclareePayee]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $client = Mockery::mock(CaisseCafabClient::class);
    $client->shouldReceive('creerDepense')->once()->andThrow(new CaisseCafabException('Connexion à Caisse CAFAB impossible.'));

    $result = (new CachetService($client))->valider($cachet, $admin);

    expect($result->statut)->toBe(StatutCachet::ValideePayee);
    expect($result->depense_creee_at)->toBeNull();
    expect($result->depense_erreur)->toBe('Connexion à Caisse CAFAB impossible.');
});

it('retries the depense creation and clears the error on success', function () {
    $cachet = Cachet::factory()->create(['id' => 1, 'statut' => StatutCachet::ValideePayee, 'depense_erreur' => 'échec précédent']);

    $client = Mockery::mock(CaisseCafabClient::class);
    $client->shouldReceive('creerDepense')->once()->andReturn('cachet-1');

    $result = (new CachetService($client))->reessayerDepense($cachet);

    expect($result->depense_creee_at)->not->toBeNull();
    expect($result->caisse_cafab_reference)->toBe('cachet-1');
    expect($result->depense_erreur)->toBeNull();
});

it('rejects retrying when the depense was already created', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::ValideePayee, 'depense_creee_at' => now()]);

    (new CachetService)->reessayerDepense($cachet);
})->throws(CachetException::class);

it('rejects retrying a cachet that was never validated', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::Du]);

    (new CachetService)->reessayerDepense($cachet);
})->throws(CachetException::class);
