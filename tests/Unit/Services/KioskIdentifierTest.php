<?php

use App\Models\Coach;
use App\Models\Fille;
use App\Services\KioskIdentifier;

it('identifies a fille by her pin', function () {
    $fille = Fille::factory()->create(['pin' => '1234']);

    $personne = (new KioskIdentifier)->identifier('1234');

    expect($personne)->not->toBeNull();
    expect($personne->is($fille))->toBeTrue();
});

it('identifies a coach by his pin', function () {
    $coach = Coach::factory()->create(['pin' => '5678']);

    $personne = (new KioskIdentifier)->identifier('5678');

    expect($personne->is($coach))->toBeTrue();
});

it('returns null for an unknown pin', function () {
    expect((new KioskIdentifier)->identifier('0000'))->toBeNull();
});
