<?php

use App\Enums\StatutCachet;
use App\Models\Cachet;
use App\Models\Fille;

it('scopeValidees only returns validee_payee cachets', function () {
    Cachet::factory()->create(['statut' => StatutCachet::ValideePayee]);
    Cachet::factory()->create(['statut' => StatutCachet::Du]);
    Cachet::factory()->create(['statut' => StatutCachet::Annule]);

    expect(Cachet::validees()->count())->toBe(1);
});

it('scopeEntrePeriode filters on validee_at', function () {
    Cachet::factory()->create(['statut' => StatutCachet::ValideePayee, 'validee_at' => '2026-09-15 10:00:00']);
    Cachet::factory()->create(['statut' => StatutCachet::ValideePayee, 'validee_at' => '2026-01-01 10:00:00']);

    $resultat = Cachet::validees()->entrePeriode('2026-09-01', '2026-09-30')->get();

    expect($resultat)->toHaveCount(1);
});

it('scopePourFille filters to one fille', function () {
    $fille = Fille::factory()->create();
    Cachet::factory()->create(['statut' => StatutCachet::ValideePayee, 'fille_id' => $fille->id]);
    Cachet::factory()->create(['statut' => StatutCachet::ValideePayee]);

    $resultat = Cachet::validees()->pourFille($fille->id)->get();

    expect($resultat)->toHaveCount(1);
});
