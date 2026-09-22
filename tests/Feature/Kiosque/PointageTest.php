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
