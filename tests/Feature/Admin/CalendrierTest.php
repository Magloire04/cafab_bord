<?php

use App\Enums\StatutSeance;
use App\Enums\UserRole;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use App\Models\User;

it('blocks a coach from the admin calendar', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.calendrier'))->assertForbidden();
});

it('shows the admin the current month by default', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('admin.calendrier'))->assertOk();
});

it('computes the taux de présence for a clôturée séance', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $seance = Seance::factory()->create(['statut' => StatutSeance::Cloturee, 'date' => now()->toDateString()]);
    $filles = Fille::factory()->count(4)->create();

    foreach ($filles as $i => $fille) {
        Pointage::factory()->create([
            'seance_id' => $seance->id,
            'pointable_type' => Fille::class,
            'pointable_id' => $fille->id,
            'statut_ponctualite' => $i === 0 ? 'absent' : 'a_l_heure',
        ]);
    }

    $response = $this->actingAs($admin)->get(route('admin.calendrier', ['mois' => now()->format('Y-m')]));

    $response->assertOk();
    $response->assertSee('75');
});
