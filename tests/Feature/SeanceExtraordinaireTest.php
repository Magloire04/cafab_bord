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

it('forces the coach_id to the acting coach, ignoring a submitted coach_id for someone else', function () {
    $coach = Coach::factory()->create();
    $coachUser = $coach->user;
    $autreCoach = Coach::factory()->create();

    $response = $this->actingAs($coachUser)->post(route('seances.store-extraordinaire'), [
        'coach_id' => $autreCoach->id,
        'date' => now()->addDay()->toDateString(),
        'heure_prevue' => '18:00',
    ]);

    $response->assertRedirect();

    $seance = Seance::where('type', TypeSeance::Extraordinaire)->latest('id')->first();
    expect($seance->coach_id)->toBe($coach->id);
    expect($seance->coach_id)->not->toBe($autreCoach->id);
});

it('lets a coach create a séance extraordinaire without sending a coach_id', function () {
    $coach = Coach::factory()->create();

    $this->actingAs($coach->user)->post(route('seances.store-extraordinaire'), [
        'date' => now()->addDay()->toDateString(),
        'heure_prevue' => '18:00',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect(Seance::where('type', TypeSeance::Extraordinaire)->where('coach_id', $coach->id)->exists())->toBeTrue();
});

it('still requires the admin to choose the coach of a séance extraordinaire', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post(route('seances.store-extraordinaire'), [
        'date' => now()->addDay()->toDateString(),
        'heure_prevue' => '18:00',
    ])->assertSessionHasErrors('coach_id');

    expect(Seance::where('type', TypeSeance::Extraordinaire)->exists())->toBeFalse();
});

it('shows a coach his own name instead of the coach list', function () {
    $coach = Coach::factory()->create();
    $coach->user->update(['name' => 'Prudence Aïvodji']);
    $autre = Coach::factory()->create();
    $autre->user->update(['name' => 'Maurice Gnonlonfoun']);

    $this->actingAs($coach->user)->get(route('seances.create-extraordinaire'))
        ->assertOk()
        ->assertSee('value="Prudence Aïvodji" disabled', false)
        ->assertDontSee('name="coach_id"', false)
        ->assertDontSee('Maurice Gnonlonfoun');
});

it('shows the admin the coach list', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Coach::factory()->create();

    $this->actingAs($admin)->get(route('seances.create-extraordinaire'))
        ->assertOk()
        ->assertSee('name="coach_id"', false);
});

it('blocks a fille-less guest from creating a séance extraordinaire', function () {
    $coach = Coach::factory()->create();

    $this->post(route('seances.store-extraordinaire'), [
        'coach_id' => $coach->id,
        'date' => now()->addDay()->toDateString(),
        'heure_prevue' => '18:00',
    ])->assertRedirect(route('login'));
});
