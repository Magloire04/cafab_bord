<?php

use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\PlanningRepetition;
use App\Models\Seance;
use App\Models\User;
use App\Services\SeanceGenerator;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from managing plannings', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.plannings.index'))->assertForbidden();
});

it('lets the admin list plannings', function () {
    PlanningRepetition::factory()->count(2)->create();

    $this->actingAs($this->admin)->get(route('admin.plannings.index'))->assertOk();
});

it('lets the admin create a planning slot', function () {
    $coach = Coach::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('admin.plannings.store'), [
        'jour_semaine' => 2,
        'heure_debut' => '17:00',
        'coach_id' => $coach->id,
    ]);

    $response->assertRedirect(route('admin.plannings.index'));
    expect(PlanningRepetition::where('coach_id', $coach->id)->where('jour_semaine', 2)->exists())->toBeTrue();
});

it('lets the admin update a planning slot', function () {
    $planning = PlanningRepetition::factory()->create(['heure_debut' => '17:00']);
    $newCoach = Coach::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.plannings.update', $planning), [
            'jour_semaine' => $planning->jour_semaine->value,
            'heure_debut' => '18:30',
            'coach_id' => $newCoach->id,
        ])
        ->assertRedirect(route('admin.plannings.index'));

    expect($planning->fresh()->heure_debut)->toBe('18:30:00');
});

it('lets the admin deactivate then reactivate a planning slot', function () {
    $planning = PlanningRepetition::factory()->create(['actif' => true]);

    $this->actingAs($this->admin)->patch(route('admin.plannings.toggle-actif', $planning));
    expect($planning->fresh()->actif)->toBeFalse();

    $this->actingAs($this->admin)->patch(route('admin.plannings.toggle-actif', $planning));
    expect($planning->fresh()->actif)->toBeTrue();
});

it('removes and regenerates a planning\'s future séances with the new time when it is updated', function () {
    Carbon::setTestNow('2026-09-22 08:00:00'); // a Tuesday

    $planning = PlanningRepetition::factory()->create(['jour_semaine' => 2, 'heure_debut' => '17:00:00', 'actif' => true]);
    app(SeanceGenerator::class)->genererPourLesProchainsJours();

    $futureAvant = Seance::where('planning_repetition_id', $planning->id)
        ->where('date', '>', today())
        ->get();
    expect($futureAvant)->not->toBeEmpty();
    expect($futureAvant->pluck('id')->all())->each->not->toBeNull();

    $this->actingAs($this->admin)->put(route('admin.plannings.update', $planning), [
        'jour_semaine' => 2,
        'heure_debut' => '18:30',
        'coach_id' => $planning->coach_id,
    ])->assertRedirect(route('admin.plannings.index'));

    $futureApres = Seance::where('planning_repetition_id', $planning->id)
        ->where('date', '>', today())
        ->get();

    // The old future séance ids are gone (deleted and regenerated), and
    // every remaining future séance now carries the corrected time.
    expect($futureApres->pluck('id')->all())->not->toEqual($futureAvant->pluck('id')->all());
    expect($futureApres)->not->toBeEmpty();
    $futureApres->each(fn (Seance $seance) => expect($seance->heure_prevue)->toBe('18:30:00'));

    Carbon::setTestNow();
});

it('generates the new créneau\'s upcoming séances immediately', function () {
    Carbon::setTestNow('2026-09-26 10:37:00'); // samedi, comme la correction #9
    $coach = Coach::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.plannings.store'), [
        'jour_semaine' => 1,
        'heure_debut' => '17:00',
        'coach_id' => $coach->id,
    ]);

    expect(Seance::where('coach_id', $coach->id)->whereDate('date', '2026-09-28')->where('statut', 'a_venir')->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('removes a planning\'s future séances when it is deactivated, and does not regenerate them', function () {
    Carbon::setTestNow('2026-09-22 08:00:00'); // a Tuesday

    $planning = PlanningRepetition::factory()->create(['jour_semaine' => 2, 'heure_debut' => '17:00:00', 'actif' => true]);
    app(SeanceGenerator::class)->genererPourLesProchainsJours();

    expect(Seance::where('planning_repetition_id', $planning->id)->where('date', '>', today())->exists())->toBeTrue();

    $this->actingAs($this->admin)->patch(route('admin.plannings.toggle-actif', $planning));

    expect($planning->fresh()->actif)->toBeFalse();
    expect(Seance::where('planning_repetition_id', $planning->id)->where('date', '>', today())->exists())->toBeFalse();

    Carbon::setTestNow();
});
