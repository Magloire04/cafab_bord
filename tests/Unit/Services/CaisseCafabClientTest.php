<?php

use App\Exceptions\CaisseCafabException;
use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Prestation;
use App\Services\CaisseCafabClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.caisse_cafab.url' => 'https://caisse.cafab.test',
        'services.caisse_cafab.token' => 'test-token',
    ]);
});

it('posts the cachet as a depense and returns the external_reference on success', function () {
    Http::fake(['caisse.cafab.test/*' => Http::response(['id' => 42, 'external_reference' => 'cachet-1'], 201)]);

    $fille = Fille::factory()->create(['nom' => 'Dupont', 'prenom' => 'Awa']);
    $prestation = Prestation::factory()->create(['titre' => 'Spectacle', 'date' => '2026-09-20']);
    $cachet = Cachet::factory()->create(['id' => 1, 'prestation_id' => $prestation->id, 'fille_id' => $fille->id, 'montant' => 5000]);

    $reference = (new CaisseCafabClient)->creerDepense($cachet);

    expect($reference)->toBe('cachet-1');
    Http::assertSent(function ($request) {
        return $request->url() === 'https://caisse.cafab.test/api/operations'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['external_reference'] === 'cachet-1'
            && $request['montant'] === 5000.0
            && $request['date_operation'] === '2026-09-20';
    });
});

it('throws when Caisse CAFAB responds with an error', function () {
    Http::fake(['caisse.cafab.test/*' => Http::response(['message' => 'invalid'], 422)]);

    $cachet = Cachet::factory()->create(['id' => 1]);

    (new CaisseCafabClient)->creerDepense($cachet);
})->throws(CaisseCafabException::class);

it('throws when the connection fails', function () {
    Http::fake(['caisse.cafab.test/*' => fn () => throw new ConnectionException('timed out')]);

    $cachet = Cachet::factory()->create(['id' => 1]);

    (new CaisseCafabClient)->creerDepense($cachet);
})->throws(CaisseCafabException::class);
