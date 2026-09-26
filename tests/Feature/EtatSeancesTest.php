<?php

use App\Enums\StatutPonctualite;
use App\Enums\StatutSeance;
use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-26 17:10:00'); // samedi
    $this->coach = Coach::factory()->create();
    $this->coach->user->update(['name' => 'Prudence Aïvodji']);
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

afterEach(fn () => Carbon::setTestNow());

it('answers 401 JSON to a guest', function () {
    $this->getJson(route('etat-seances'))->assertUnauthorized();
});

it('shows the admin every séance in progress with its attendance and its coach', function () {
    $seance = Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-09-26', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::EnCours]);
    [$presente] = Fille::factory()->count(2)->create(['statut' => 'actif']);
    Pointage::factory()->create(['seance_id' => $seance->id, 'pointable_id' => $presente->id, 'statut_ponctualite' => StatutPonctualite::ALHeure]);

    $this->actingAs($this->admin)->getJson(route('etat-seances'))
        ->assertOk()
        ->assertExactJson(['lignes' => [[
            'id' => $seance->id,
            'type' => 'en_cours',
            'texte' => 'Répétition en cours depuis 17:00 · 1 présente sur 2 · Prudence Aïvodji',
        ]]]);
});

it('shows a coach only his own séances, without his own name', function () {
    $autreCoach = Coach::factory()->create();
    Seance::factory()->create(['coach_id' => $autreCoach->id, 'date' => '2026-09-26', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::EnCours]);
    $lundi = Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-09-28', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    $this->actingAs($this->coach->user)->getJson(route('etat-seances'))
        ->assertOk()
        ->assertExactJson(['lignes' => [[
            'id' => $lundi->id,
            'type' => 'prochaine',
            'texte' => 'Prochaine répétition : lundi 28 septembre à 17:00',
        ]]]);
});

it('says aujourd\'hui for a séance later today', function () {
    Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-09-26', 'heure_prevue' => '19:00:00', 'statut' => StatutSeance::AVenir]);

    $this->actingAs($this->admin)->getJson(route('etat-seances'))
        ->assertOk()
        ->assertJsonPath('lignes.0.texte', "Prochaine répétition : aujourd'hui à 19:00 · Prudence Aïvodji");
});

it('says demain for tomorrow\'s séance', function () {
    Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-09-27', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    $this->actingAs($this->admin)->getJson(route('etat-seances'))
        ->assertOk()
        ->assertJsonPath('lignes.0.texte', 'Prochaine répétition : demain à 17:00 · Prudence Aïvodji');
});

it('ignores séances beyond the 14-day horizon', function () {
    Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-10-15', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    $this->actingAs($this->admin)->getJson(route('etat-seances'))->assertExactJson(['lignes' => []]);
});

it('returns an empty state for a coach account without a coach profile', function () {
    Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-09-26', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::EnCours]);
    $sansProfil = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($sansProfil)->getJson(route('etat-seances'))->assertOk()->assertExactJson(['lignes' => []]);
});

it('renders the banner server-side on the dashboard', function () {
    Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-09-26', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::EnCours]);

    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Répétition en cours depuis 17:00')
        ->assertSee('data-bandeau-seance', false);
});
