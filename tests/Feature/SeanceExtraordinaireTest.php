<?php

use App\Enums\TypeSeance;
use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\Seance;
use App\Models\User;

it('lets an admin create a séance extraordinaire', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $coach = Coach::factory()->create();

    $response = $this->actingAs($admin)->post(route('seances.store-extraordinaire'), [
        'coach_id' => $coach->id,
        'date' => now()->addDay()->toDateString(),
        'heure_prevue' => '18:00',
    ]);

    $response->assertRedirect();
    expect(Seance::where('type', TypeSeance::Extraordinaire)->where('coach_id', $coach->id)->exists())->toBeTrue();
});

it('lets a coach create a séance extraordinaire', function () {
    $coach = Coach::factory()->create();
    $coachUser = $coach->user;

    $response = $this->actingAs($coachUser)->post(route('seances.store-extraordinaire'), [
        'coach_id' => $coach->id,
        'date' => now()->addDay()->toDateString(),
        'heure_prevue' => '18:00',
    ]);

    $response->assertRedirect();
});

it('blocks a fille-less guest from creating a séance extraordinaire', function () {
    $coach = Coach::factory()->create();

    $this->post(route('seances.store-extraordinaire'), [
        'coach_id' => $coach->id,
        'date' => now()->addDay()->toDateString(),
        'heure_prevue' => '18:00',
    ])->assertRedirect(route('login'));
});
