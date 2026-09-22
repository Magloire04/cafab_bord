<?php

use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\PlanningRepetition;
use App\Models\User;

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
