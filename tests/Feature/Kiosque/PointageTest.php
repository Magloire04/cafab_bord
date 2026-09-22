<?php

use App\Enums\SourcePointage;
use App\Enums\StatutSeance;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;

it('lets an identified fille pointer her presence at the séance en cours', function () {
    $fille = Fille::factory()->create(['pin' => '1234']);
    $seance = Seance::factory()->create(['statut' => StatutSeance::EnCours]);

    $this->post(route('kiosque.identifier'), ['pin' => '1234']);

    $response = $this->post(route('kiosque.pointer'));

    $response->assertOk();
    $pointage = Pointage::where('pointable_type', Fille::class)->where('pointable_id', $fille->id)->first();
    expect($pointage)->not->toBeNull();
    expect($pointage->source)->toBe(SourcePointage::Auto);
});

it('refuses to pointer without an identified session', function () {
    Seance::factory()->create(['statut' => StatutSeance::EnCours]);

    $this->post(route('kiosque.pointer'))->assertRedirect(route('kiosque.home'));
});

it('shows a friendly message rather than a 500 when no séance is en cours', function () {
    $fille = Fille::factory()->create(['pin' => '1234']);
    $this->post(route('kiosque.identifier'), ['pin' => '1234']);

    $response = $this->post(route('kiosque.pointer'));

    $response->assertOk();
    $response->assertSee('Aucune répétition en cours', false);
});

it('clears the kiosk session even when there is no séance en cours', function () {
    $fille = Fille::factory()->create(['pin' => '1234']);
    $this->post(route('kiosque.identifier'), ['pin' => '1234']);

    $this->post(route('kiosque.pointer'));

    expect(session('kiosque.identifie'))->toBeNull();
    expect(session('kiosque.nom'))->toBeNull();
});

it('shows a friendly message and clears the session when the person already pointed', function () {
    $fille = Fille::factory()->create(['pin' => '1234']);
    $seance = Seance::factory()->create(['statut' => StatutSeance::EnCours]);
    Pointage::factory()->create([
        'seance_id' => $seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
    ]);

    $this->post(route('kiosque.identifier'), ['pin' => '1234']);

    $response = $this->post(route('kiosque.pointer'));

    $response->assertOk();
    $response->assertSee('déjà', false);
    expect(session('kiosque.identifie'))->toBeNull();
    expect(session('kiosque.nom'))->toBeNull();
});

it('redirects home without a 500 when the identified person no longer exists', function () {
    $fille = Fille::factory()->create(['pin' => '1234']);
    Seance::factory()->create(['statut' => StatutSeance::EnCours]);
    $this->post(route('kiosque.identifier'), ['pin' => '1234']);
    $fille->delete();

    $response = $this->post(route('kiosque.pointer'));

    $response->assertRedirect(route('kiosque.home'));
    expect(session('kiosque.identifie'))->toBeNull();
});
