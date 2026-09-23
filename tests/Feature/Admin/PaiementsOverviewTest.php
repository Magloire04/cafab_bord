<?php

use App\Enums\StatutCachet;
use App\Enums\UserRole;
use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Prestation;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from the payments overview', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.paiements.index'))->assertForbidden();
});

it('shows the total due, paid, and remaining per prestation', function () {
    $prestation = Prestation::factory()->create(['titre' => 'Spectacle', 'date' => '2026-09-10']);
    Cachet::factory()->create(['prestation_id' => $prestation->id, 'montant' => 5000, 'statut' => StatutCachet::ValideePayee]);
    Cachet::factory()->create(['prestation_id' => $prestation->id, 'montant' => 3000, 'statut' => StatutCachet::Du]);

    $response = $this->actingAs($this->admin)->get(route('admin.paiements.index'));

    $response->assertOk();
    $response->assertSee('Spectacle');
    $response->assertSeeText('8000');
    $response->assertSeeText('5000');
    $response->assertSeeText('3000');
});

it('excludes annule cachets from the totals', function () {
    $prestation = Prestation::factory()->create(['titre' => 'Gala']);
    Cachet::factory()->create(['prestation_id' => $prestation->id, 'montant' => 5000, 'statut' => StatutCachet::Annule]);

    $response = $this->actingAs($this->admin)->get(route('admin.paiements.index'));

    $response->assertOk()->assertDontSeeText('5000');
});

it('filters by period', function () {
    Prestation::factory()->create(['titre' => 'Dans la période', 'date' => '2026-09-15']);
    Prestation::factory()->create(['titre' => 'Hors période', 'date' => '2026-01-01']);

    $response = $this->actingAs($this->admin)->get(route('admin.paiements.index', [
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-09-30',
    ]));

    $response->assertSee('Dans la période')->assertDontSee('Hors période');
});

it('filters by fille', function () {
    $fille = Fille::factory()->create();
    $autreFille = Fille::factory()->create();
    $avecFille = Prestation::factory()->create(['titre' => 'Avec la fille']);
    $sansFille = Prestation::factory()->create(['titre' => 'Sans la fille']);
    Cachet::factory()->create(['prestation_id' => $avecFille->id, 'fille_id' => $fille->id]);
    Cachet::factory()->create(['prestation_id' => $sansFille->id, 'fille_id' => $autreFille->id]);

    $response = $this->actingAs($this->admin)->get(route('admin.paiements.index', ['fille_id' => $fille->id]));

    $response->assertSee('Avec la fille')->assertDontSee('Sans la fille');
});
