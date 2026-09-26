<?php

use App\Enums\StatutCachet;
use App\Enums\UserRole;
use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Prestation;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->prestation = Prestation::factory()->create(['titre' => 'Nuit du Patrimoine']);
});

it('shows the four indicators of the prestation', function () {
    Cachet::factory()->create(['prestation_id' => $this->prestation->id, 'montant' => 15000, 'statut' => StatutCachet::ValideePayee]);
    Cachet::factory()->create(['prestation_id' => $this->prestation->id, 'montant' => 20000, 'statut' => StatutCachet::DeclareePayee]);
    Cachet::factory()->create(['prestation_id' => $this->prestation->id, 'montant' => 9000, 'statut' => StatutCachet::Annule]);

    $this->actingAs($this->admin)->get(route('admin.prestations.show', $this->prestation))
        ->assertOk()
        ->assertViewHas('indicateurs', ['total_du' => 35000.0, 'valide' => 15000.0, 'reste' => 20000.0, 'a_traiter' => 1])
        ->assertSee('35 000 F')
        ->assertSee('Détail par fille');
});

it('offers Valider and a menu opening the adjust and correct forms for an open cachet', function () {
    $cachet = Cachet::factory()->create([
        'prestation_id' => $this->prestation->id,
        'fille_id' => Fille::factory()->create(['prenom' => 'Sènami', 'nom' => 'Hounkpatin'])->id,
        'statut' => StatutCachet::DeclareePayee,
        'declaree_at' => Carbon::parse('2026-09-14 18:00:00'),
    ]);

    $this->actingAs($this->admin)->get(route('admin.prestations.show', $this->prestation))
        ->assertSee('Déclarée payée · 14 sept.')
        ->assertSee('Valider')
        ->assertSee('#modale-ajuster-'.$cachet->id, false)
        ->assertSee('#modale-corriger-'.$cachet->id, false);
});

it('shows no action on a validated cachet', function () {
    $cachet = Cachet::factory()->create(['prestation_id' => $this->prestation->id, 'statut' => StatutCachet::ValideePayee]);

    $this->actingAs($this->admin)->get(route('admin.prestations.show', $this->prestation))
        ->assertDontSee('modale-ajuster-'.$cachet->id, false);
});
