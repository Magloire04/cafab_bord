<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('gives an account a provisional password to change at next login', function () {
    $user = User::factory()->create(['email' => 'admin@cafab.bj']);

    $this->artisan('users:reset-password', ['email' => ' Admin@CAFAB.bj '])
        ->expectsOutputToContain('Mot de passe provisoire pour')
        ->assertSuccessful();

    $user->refresh();
    expect($user->must_change_password)->toBeTrue();
    expect(Hash::check('password', $user->password))->toBeFalse();
});

it('fails for an unknown address', function () {
    $this->artisan('users:reset-password', ['email' => 'inconnu@cafab.bj'])->assertFailed();
});
