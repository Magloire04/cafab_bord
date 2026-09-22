<?php

use App\Enums\StatutSeance;
use App\Enums\UserRole;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Support\Carbon;

it('blocks a coach from the admin calendar', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.calendrier'))->assertForbidden();
});

it('shows the admin the current month by default', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('admin.calendrier'))->assertOk();
});

it('rejects a malformed mois query param instead of 500ing', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.calendrier', ['mois' => 'abc']))
        ->assertSessionHasErrors('mois');
});

it('resolves ?mois= on a day-of-month that does not exist in the target month, without overflowing', function () {
    Carbon::setTestNow('2026-01-31');
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->get(route('admin.calendrier', ['mois' => '2026-02']));

    $response->assertOk();
    $response->assertViewHas('mois', fn ($mois) => $mois->format('Y-m') === '2026-02');

    Carbon::setTestNow();
});

it('computes the taux de présence for a clôturée séance', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $seance = Seance::factory()->create(['statut' => StatutSeance::EnCours, 'date' => now()->toDateString()]);
    $filles = Fille::factory()->count(4)->create();

    // Only 3 of the 4 filles self-pointed before clôture; Seance::clore()
    // is expected to materialize the 4th as "absent" — exercising the
    // real clôture path rather than fabricating the absence by hand keeps
    // this test honest about where the absence actually comes from.
    foreach ($filles->take(3) as $fille) {
        Pointage::factory()->create([
            'seance_id' => $seance->id,
            'pointable_type' => Fille::class,
            'pointable_id' => $fille->id,
            'statut_ponctualite' => 'a_l_heure',
        ]);
    }

    $seance->clore();

    $response = $this->actingAs($admin)->get(route('admin.calendrier', ['mois' => now()->format('Y-m')]));

    $response->assertOk();
    $response->assertSee('75');
});
