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

it('blocks a coach from the depenses report', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.rapports.depenses'))->assertForbidden();
});

it('lets the admin view validated cachets with prestation and fille detail', function () {
    $fille = Fille::factory()->create(['prenom' => 'Awa', 'nom' => 'Dupont']);
    $prestation = Prestation::factory()->create(['titre' => 'Spectacle']);
    Cachet::factory()->create([
        'prestation_id' => $prestation->id, 'fille_id' => $fille->id,
        'statut' => StatutCachet::ValideePayee, 'montant' => 5000, 'validee_at' => now(),
    ]);

    $this->actingAs($this->admin)->get(route('admin.rapports.depenses'))
        ->assertOk()
        ->assertSee('Awa Dupont')
        ->assertSee('Spectacle')
        ->assertSeeText('5000');
});

it('excludes non validated cachets and shows the correct total', function () {
    Cachet::factory()->create(['statut' => StatutCachet::ValideePayee, 'montant' => 5000, 'validee_at' => now()]);
    Cachet::factory()->create(['statut' => StatutCachet::Du, 'montant' => 3000]);

    $response = $this->actingAs($this->admin)->get(route('admin.rapports.depenses'));

    $response->assertOk()->assertSeeText('5000');
    $response->assertDontSeeText('3000');
});

it('filters by period and by fille', function () {
    $fille = Fille::factory()->create();
    $autreFille = Fille::factory()->create();
    Cachet::factory()->create(['statut' => StatutCachet::ValideePayee, 'fille_id' => $fille->id, 'validee_at' => '2026-09-15']);
    Cachet::factory()->create(['statut' => StatutCachet::ValideePayee, 'fille_id' => $autreFille->id, 'validee_at' => '2026-01-01']);

    $response = $this->actingAs($this->admin)->get(route('admin.rapports.depenses', [
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-09-30',
        'fille_id' => $fille->id,
    ]));

    $response->assertOk();
});

it('exports the depenses report as excel', function () {
    Cachet::factory()->create(['statut' => StatutCachet::ValideePayee, 'validee_at' => now()]);

    $this->actingAs($this->admin)->get(route('admin.rapports.depenses.excel'))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('shows the rapports hub with a link to depenses', function () {
    $this->actingAs($this->admin)->get(route('admin.rapports.index'))
        ->assertOk()
        ->assertSee(route('admin.rapports.depenses'), false);
});
