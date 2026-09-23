<?php

use App\Enums\StatutCachet;
use App\Enums\StatutPrestation;
use App\Enums\UserRole;
use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Prestation;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from managing prestations', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.prestations.index'))->assertForbidden();
});

it('lets the admin list prestations', function () {
    Prestation::factory()->count(2)->create();

    $this->actingAs($this->admin)->get(route('admin.prestations.index'))->assertOk();
});

it('lets the admin create a prestation and affect filles with per-fille montant overrides', function () {
    $fille1 = Fille::factory()->create();
    $fille2 = Fille::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('admin.prestations.store'), [
        'titre' => 'Spectacle de fin d\'année',
        'lieu' => 'Palais des Congrès',
        'date' => now()->addWeek()->toDateString(),
        'montant_defaut' => 5000,
        'fille_ids' => [$fille1->id, $fille2->id],
        'montants' => [$fille2->id => 7500],
    ]);

    $response->assertRedirect(route('admin.prestations.index'));

    $prestation = Prestation::where('titre', 'Spectacle de fin d\'année')->firstOrFail();
    expect($prestation->cachets)->toHaveCount(2);
    expect(Cachet::where('prestation_id', $prestation->id)->where('fille_id', $fille1->id)->first()->montant)->toBe('5000.00');
    expect(Cachet::where('prestation_id', $prestation->id)->where('fille_id', $fille2->id)->first()->montant)->toBe('7500.00');
    expect(Cachet::where('prestation_id', $prestation->id)->first()->statut)->toBe(StatutCachet::Du);
});

it('rolls back the whole prestation when fille_ids contains a duplicate', function () {
    $fille = Fille::factory()->create();

    $this->withoutExceptionHandling();

    try {
        $this->actingAs($this->admin)->post(route('admin.prestations.store'), [
            'titre' => 'Doublon',
            'lieu' => 'Palais des Congrès',
            'date' => now()->addWeek()->toDateString(),
            'montant_defaut' => 5000,
            'fille_ids' => [$fille->id, $fille->id],
        ]);
    } catch (Throwable $e) {
        // A duplicate fille_id is expected to throw (no dedupe validation); the point of this
        // test is that the transaction leaves no partial data behind, not the exception itself.
    }

    expect(Prestation::where('titre', 'Doublon')->exists())->toBeFalse();
    expect(Cachet::where('fille_id', $fille->id)->exists())->toBeFalse();
});

it('lets the admin view a prestation with its cachets', function () {
    $prestation = Prestation::factory()->create();
    Cachet::factory()->create(['prestation_id' => $prestation->id]);

    $this->actingAs($this->admin)->get(route('admin.prestations.show', $prestation))->assertOk();
});

it('cancelling a prestation annule its non-finalized cachets but leaves validated ones untouched', function () {
    $prestation = Prestation::factory()->create();
    $enAttente = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::Du]);
    $validee = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::ValideePayee]);

    $this->actingAs($this->admin)->patch(route('admin.prestations.annuler', $prestation))
        ->assertRedirect(route('admin.prestations.index'));

    expect($prestation->fresh()->statut)->toBe(StatutPrestation::Annulee);
    expect($enAttente->fresh()->statut)->toBe(StatutCachet::Annule);
    expect($validee->fresh()->statut)->toBe(StatutCachet::ValideePayee);
});
