<?php

use App\Enums\UserRole;
use App\Models\User;

it('creates a coach account with a random password', function () {
    $this->artisan('users:create', [
        'name' => 'Prudence Aïvodji',
        'email' => 'prudence@cafab.bj',
        '--role' => 'coach',
    ])->assertSuccessful();

    $user = User::where('email', 'prudence@cafab.bj')->firstOrFail();

    expect($user->name)->toBe('Prudence Aïvodji');
    expect($user->role)->toBe(UserRole::Coach);
    expect($user->must_change_password)->toBeTrue();
});

it('rejects an invalid role', function () {
    $this->artisan('users:create', [
        'name' => 'Test',
        'email' => 'test@cafab.bj',
        '--role' => 'invalide',
    ])->assertFailed();

    expect(User::where('email', 'test@cafab.bj')->exists())->toBeFalse();
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'existing@cafab.bj']);

    $this->artisan('users:create', [
        'name' => 'Autre',
        'email' => 'existing@cafab.bj',
        '--role' => 'admin',
    ])->assertFailed();
});
