<?php

use App\Enums\StatutCachet;
use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Prestation;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-23 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('lists only past, non-finalised cachets for the identified fille', function () {
    $fille = Fille::factory()->create();
    $passee = Prestation::factory()->create(['date' => '2026-09-22']);
    $future = Prestation::factory()->create(['date' => '2026-09-24']);
    $passee2 = Prestation::factory()->create(['date' => '2026-09-21']);

    $eligible = Cachet::factory()->create(['prestation_id' => $passee->id, 'fille_id' => $fille->id, 'statut' => StatutCachet::Du]);
    Cachet::factory()->create(['prestation_id' => $future->id, 'fille_id' => $fille->id, 'statut' => StatutCachet::Du]);
    Cachet::factory()->create(['prestation_id' => $passee2->id, 'fille_id' => $fille->id, 'statut' => StatutCachet::ValideePayee]);

    $this->withSession([
        'kiosque.identifie' => ['type' => Fille::class, 'id' => $fille->id],
        'kiosque.nom' => "{$fille->prenom} {$fille->nom}",
    ])->get(route('kiosque.cachets.index'))
        ->assertOk()
        ->assertSee($passee->titre)
        ->assertDontSee($future->titre);
});

it('redirects to the menu when nobody is identified', function () {
    $this->get(route('kiosque.cachets.index'))->assertRedirect(route('kiosque.menu'));
});

it('lets the identified fille declare having received her cachet', function () {
    $fille = Fille::factory()->create();
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'fille_id' => $fille->id, 'statut' => StatutCachet::Du]);

    $response = $this->withSession([
        'kiosque.identifie' => ['type' => Fille::class, 'id' => $fille->id],
        'kiosque.nom' => "{$fille->prenom} {$fille->nom}",
    ])->post(route('kiosque.cachets.declarer', $cachet), ['recu' => '1']);

    $response->assertOk();
    expect($cachet->fresh()->statut)->toBe(StatutCachet::DeclareePayee);
});

it('blocks declaring for someone else\'s cachet', function () {
    $fille = Fille::factory()->create();
    $autreFille = Fille::factory()->create();
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'fille_id' => $autreFille->id, 'statut' => StatutCachet::Du]);

    $this->withSession([
        'kiosque.identifie' => ['type' => Fille::class, 'id' => $fille->id],
        'kiosque.nom' => "{$fille->prenom} {$fille->nom}",
    ])->post(route('kiosque.cachets.declarer', $cachet), ['recu' => '1'])
        ->assertForbidden();
});
