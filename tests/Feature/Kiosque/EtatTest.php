<?php

use App\Enums\StatutSeance;
use App\Models\Coach;
use App\Models\Seance;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-26 12:00:00'); // samedi
    $this->coach = Coach::factory()->create();
    $this->coach->user->update(['name' => 'Prudence Aïvodji']);
});

afterEach(fn () => Carbon::setTestNow());

it('exposes the next répétition without any name', function () {
    $lundi = Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-09-28', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    $this->getJson(route('kiosque.etat'))
        ->assertOk()
        ->assertExactJson([
            'en_cours_id' => null,
            'prochaine' => ['id' => $lundi->id, 'texte' => 'Prochaine répétition : lundi 28 septembre à 17:00'],
        ])
        ->assertDontSee('Prudence');
});

it('exposes the séance in progress', function () {
    $enCours = Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-09-26', 'heure_prevue' => '11:30:00', 'statut' => StatutSeance::EnCours]);

    $this->getJson(route('kiosque.etat'))->assertOk()->assertJsonPath('en_cours_id', $enCours->id);
});
