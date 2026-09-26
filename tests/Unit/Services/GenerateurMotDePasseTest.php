<?php

use App\Services\GenerateurMotDePasse;

it('generates 10-character passwords mixing every character class', function () {
    $generateur = new GenerateurMotDePasse;

    foreach (range(1, 200) as $tour) {
        $motDePasse = $generateur->generer();

        expect(strlen($motDePasse))->toBe(10)
            ->and($motDePasse)->toMatch('/[A-Z]/')
            ->and($motDePasse)->toMatch('/[a-z]/')
            ->and($motDePasse)->toMatch('/[0-9]/')
            ->and($motDePasse)->toMatch('/[^A-Za-z0-9]/');
    }
});

it('passes its own validation rule', function () {
    $motDePasse = (new GenerateurMotDePasse)->generer();

    expect(validator(['p' => $motDePasse], ['p' => GenerateurMotDePasse::regle()])->passes())->toBeTrue();
});

it('rejects a weak password with the provisional rule', function () {
    expect(validator(['p' => 'Motdepasse1'], ['p' => GenerateurMotDePasse::regle()])->passes())->toBeFalse();
});

it('does not repeat itself', function () {
    $generateur = new GenerateurMotDePasse;

    expect(collect(range(1, 50))->map(fn () => $generateur->generer())->unique())->toHaveCount(50);
});

it('refuses a length below the provisional rule minimum', function () {
    expect(fn () => (new GenerateurMotDePasse)->generer(9))->toThrow(InvalidArgumentException::class);
});

it('accepts a longer length', function () {
    expect(strlen((new GenerateurMotDePasse)->generer(16)))->toBe(16);
});
