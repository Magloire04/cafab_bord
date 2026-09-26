<?php

use App\Enums\StatutSeance;

it('gives each séance status a French label', function (StatutSeance $statut, string $libelle) {
    expect($statut->libelle())->toBe($libelle);
})->with([
    [StatutSeance::AVenir, 'À venir'],
    [StatutSeance::EnCours, 'En cours'],
    [StatutSeance::Cloturee, 'Clôturée'],
]);
