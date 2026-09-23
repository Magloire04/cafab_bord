<?php

use App\Enums\StatutPonctualite;
use App\Enums\UserRole;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from the ponctualite report', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.rapports.ponctualite'))->assertForbidden();
});

it('lets the admin view the ponctualite report', function () {
    $fille = Fille::factory()->create(['prenom' => 'Awa', 'nom' => 'Dupont']);
    $seance = Seance::factory()->create(['date' => '2026-09-15']);
    Pointage::factory()->create(['seance_id' => $seance->id, 'pointable_type' => Fille::class, 'pointable_id' => $fille->id, 'statut_ponctualite' => StatutPonctualite::ALHeure]);

    $this->actingAs($this->admin)->get(route('admin.rapports.ponctualite'))
        ->assertOk()
        ->assertSee('Awa Dupont');
});

it('exports the ponctualite report as excel', function () {
    Fille::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.rapports.ponctualite.excel'))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('shows the rapports hub with a link to ponctualite', function () {
    $this->actingAs($this->admin)->get(route('admin.rapports.index'))
        ->assertOk()
        ->assertSee(route('admin.rapports.ponctualite'), false);
});
