<?php

use App\Enums\StatutSeance;
use App\Models\Fille;
use App\Models\Seance;
use Illuminate\Support\Carbon;

it('shows the kiosk home without authentication', function () {
    $this->get(route('kiosque.home'))->assertOk();
});

it('shows the next répétition when none is in progress', function () {
    Carbon::setTestNow('2026-09-26 12:00:00');
    Seance::factory()->create(['date' => '2026-09-28', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    $this->get(route('kiosque.home'))
        ->assertOk()
        ->assertSee('Prochaine répétition : lundi 28 septembre à 17:00')
        ->assertSee('data-kiosque-etat', false);

    Carbon::setTestNow();
});

it('identifies a fille by pin and stores it in the session', function () {
    $fille = Fille::factory()->create(['pin' => '1234']);
    Seance::factory()->create(['statut' => StatutSeance::EnCours]);

    $response = $this->post(route('kiosque.identifier'), ['pin' => '1234']);

    $response->assertRedirect(route('kiosque.menu'));
    $this->assertEquals(['type' => Fille::class, 'id' => $fille->id], session('kiosque.identifie'));
});

it('rejects an unknown pin with an error, no redirect to the menu', function () {
    $response = $this->post(route('kiosque.identifier'), ['pin' => '9999']);

    $response->assertRedirect(route('kiosque.home'));
    $response->assertSessionHasErrors('pin');
    expect(session('kiosque.identifie'))->toBeNull();
});

it('rate-limits repeated pin attempts', function () {
    for ($i = 0; $i < 21; $i++) {
        $response = $this->post(route('kiosque.identifier'), ['pin' => '0000']);
    }

    $response->assertStatus(429);
});
