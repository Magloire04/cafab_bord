<?php

use App\Models\Coach;
use App\Models\Fille;
use App\Services\PinGenerator;

it('generates a 4-digit numeric pin', function () {
    $pin = (new PinGenerator)->generate();

    expect($pin)->toMatch('/^\d{4}$/');
});

it('detects a pin already used by a fille', function () {
    Fille::factory()->create(['pin' => '1111']);

    expect((new PinGenerator)->isTaken('1111'))->toBeTrue();
    expect((new PinGenerator)->isTaken('2222'))->toBeFalse();
});

it('detects a pin already used by a coach', function () {
    Coach::factory()->create(['pin' => '3333']);

    expect((new PinGenerator)->isTaken('3333'))->toBeTrue();
});

it('never generates a pin already taken by a fille or a coach', function () {
    Fille::factory()->create(['pin' => '5555']);
    Coach::factory()->create(['pin' => '6666']);

    $generator = new PinGenerator;

    for ($i = 0; $i < 200; $i++) {
        $pin = $generator->generate();
        expect($pin)->not->toBe('5555');
        expect($pin)->not->toBe('6666');
    }
});
