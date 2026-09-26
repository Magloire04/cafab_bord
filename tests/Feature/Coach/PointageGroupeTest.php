<?php

use App\Enums\SourcePointage;
use App\Enums\StatutSeance;
use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->coach = Coach::factory()->create();
    $this->coachUser = $this->coach->user;
    $this->seance = Seance::factory()->create(['coach_id' => $this->coach->id, 'statut' => StatutSeance::EnCours]);
});

it('blocks an admin from the coach roll-call screen', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('coach.seance'))->assertForbidden();
});

it('shows the current séance to its referent coach', function () {
    $this->actingAs($this->coachUser)->get(route('coach.seance'))->assertOk();
});

it('does not show a stale past-dated a_venir séance instead of the empty state', function () {
    // Regression test: before seances:cloturer also closed out stale
    // a_venir séances, a coach who forgot about a past-dated a_venir
    // séance (one that never started, since seances:demarrer only starts
    // séances dated today) would be stuck seeing it forever in "Ma
    // répétition" — with no Clôturer button rendered (estEnCours() is
    // false), and no way to reach today's state from the UI.
    $coach = Coach::factory()->create();
    $coachUser = $coach->user;
    Seance::factory()->create([
        'coach_id' => $coach->id,
        'statut' => StatutSeance::AVenir,
        'date' => now()->subDay()->toDateString(),
    ]);

    $response = $this->actingAs($coachUser)->get(route('coach.seance'));

    $response->assertOk();
    $response->assertViewHas('seance', null);
});

it('shows the coach\'s next séance even when it is on a later day', function () {
    Carbon::setTestNow('2026-09-26 12:00:00');
    $coach = Coach::factory()->create();
    $lundi = Seance::factory()->create([
        'coach_id' => $coach->id,
        'statut' => StatutSeance::AVenir,
        'date' => '2026-09-28',
        'heure_prevue' => '17:00:00',
    ]);

    $this->actingAs($coach->user)
        ->get(route('coach.seance'))
        ->assertOk()
        ->assertViewHas('seance', fn ($seance) => $seance?->is($lundi));

    Carbon::setTestNow();
});

it('lets the coach mark a fille present who has not yet self-pointed', function () {
    $fille = Fille::factory()->create();

    $response = $this->actingAs($this->coachUser)->patch(route('coach.seance.marquer-presente'), [
        'seance_id' => $this->seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
    ]);

    $response->assertRedirect();
    $pointage = Pointage::where('pointable_id', $fille->id)->first();
    expect($pointage->source)->toBe(SourcePointage::Coach);
    expect($pointage->pointe_par_user_id)->toBe($this->coachUser->id);
});

it('lets the coach backdate a pointage to a specific time rather than now', function () {
    $fille = Fille::factory()->create();

    $this->actingAs($this->coachUser)->patch(route('coach.seance.marquer-presente'), [
        'seance_id' => $this->seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
        'heure' => '17:02',
    ]);

    $pointage = Pointage::where('pointable_id', $fille->id)->first();
    expect($pointage->pointe_a->format('H:i'))->toBe('17:02');
});

it('refuses to let the coach overwrite a pointage the fille already made herself', function () {
    $fille = Fille::factory()->create();
    Pointage::factory()->create([
        'seance_id' => $this->seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
        'source' => 'auto',
    ]);

    $response = $this->actingAs($this->coachUser)->patch(route('coach.seance.marquer-presente'), [
        'seance_id' => $this->seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
    ]);

    $response->assertSessionHasErrors();
    expect(Pointage::where('pointable_id', $fille->id)->count())->toBe(1);
});

it('lets the coach clôturer the séance', function () {
    $this->actingAs($this->coachUser)->patch(route('coach.seance.cloturer'), ['seance_id' => $this->seance->id]);

    expect($this->seance->fresh()->statut)->toBe(StatutSeance::Cloturee);
    expect($this->seance->fresh()->cloture_par_user_id)->toBe($this->coachUser->id);
});

it('blocks a coach from marking presence on a séance that is not theirs', function () {
    $autreCoach = Coach::factory()->create();
    $fille = Fille::factory()->create();

    $this->actingAs($autreCoach->user)->patch(route('coach.seance.marquer-presente'), [
        'seance_id' => $this->seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
    ])->assertForbidden();
});

it('blocks a coach from clôturing a séance that is not theirs', function () {
    $autreCoach = Coach::factory()->create();

    $this->actingAs($autreCoach->user)->patch(route('coach.seance.cloturer'), [
        'seance_id' => $this->seance->id,
    ])->assertForbidden();

    expect($this->seance->fresh()->statut)->toBe(StatutSeance::EnCours);
});
