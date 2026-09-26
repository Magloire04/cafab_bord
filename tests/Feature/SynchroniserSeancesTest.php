<?php

use App\Enums\StatutSeance;
use App\Models\Seance;
use App\Services\SeanceCycleDeVie;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Exceptions;

afterEach(fn () => Carbon::setTestNow());

it('synchronises séances on a page request when enabled', function () {
    config(['seances.synchronisation_auto' => true]);
    Carbon::setTestNow('2026-09-22 17:05:00');
    $seance = Seance::factory()->create(['date' => '2026-09-22', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    $this->get(route('kiosque.home'))->assertOk();

    expect($seance->fresh()->statut)->toBe(StatutSeance::EnCours);
});

it('synchronises at most once a minute', function () {
    config(['seances.synchronisation_auto' => true]);
    Carbon::setTestNow('2026-09-22 17:05:00');
    $this->get(route('kiosque.home'));

    $seance = Seance::factory()->create(['date' => '2026-09-22', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);
    $this->get(route('kiosque.home'));
    expect($seance->fresh()->statut)->toBe(StatutSeance::AVenir);

    Carbon::setTestNow('2026-09-22 17:06:01');
    $this->get(route('kiosque.home'));
    expect($seance->fresh()->statut)->toBe(StatutSeance::EnCours);
});

it('does nothing when the automatic synchronisation is disabled', function () {
    config(['seances.synchronisation_auto' => false]);
    Carbon::setTestNow('2026-09-22 17:05:00');
    $seance = Seance::factory()->create(['date' => '2026-09-22', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    $this->get(route('kiosque.home'));

    expect($seance->fresh()->statut)->toBe(StatutSeance::AVenir);
});

it('reports a synchronisation failure without breaking the page', function () {
    config(['seances.synchronisation_auto' => true]);
    Exceptions::fake();
    $this->mock(SeanceCycleDeVie::class)->shouldReceive('synchroniser')->andThrow(new RuntimeException('base indisponible'));

    $this->get(route('kiosque.home'))->assertOk();

    Exceptions::assertReported(RuntimeException::class);
});
