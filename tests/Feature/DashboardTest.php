<?php

use App\Enums\StatutSeance;
use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\Seance;
use App\Models\User;

it('keeps only the indicators on the admin dashboard', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Coachs actifs')
        ->assertDontSee('link-tile', false);
});

it('addresses the coach formally on his dashboard', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Consultez vos séances passées et votre ponctualité.')
        ->assertDontSee('Consulte tes');
});

it('shows the status of today\'s séance in words on the coach dashboard', function () {
    $coach = Coach::factory()->create();
    Seance::factory()->create([
        'coach_id' => $coach->id,
        'date' => today()->toDateString(),
        'statut' => StatutSeance::EnCours,
    ]);

    $this->actingAs($coach->user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Statut : En cours')
        ->assertDontSee('Statut : en_cours');
});
