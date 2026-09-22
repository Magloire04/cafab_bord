<?php

use App\Enums\StatutPersonne;
use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\User;
use Illuminate\Database\QueryException;

it('belongs to a user account', function () {
    $user = User::factory()->create(['role' => UserRole::Coach]);
    $coach = Coach::factory()->create(['user_id' => $user->id]);

    expect($coach->user->is($user))->toBeTrue();
});

it('casts statut to the StatutPersonne enum and defaults to actif', function () {
    $coach = Coach::factory()->create();

    expect($coach->statut)->toBe(StatutPersonne::Actif);
});

it('rejects a duplicate pin', function () {
    Coach::factory()->create(['pin' => '1234']);

    Coach::factory()->create(['pin' => '1234']);
})->throws(QueryException::class);

it('blocks deleting the linked user account', function () {
    $user = User::factory()->create(['role' => UserRole::Coach]);
    Coach::factory()->create(['user_id' => $user->id]);

    $user->delete();
})->throws(QueryException::class);

it('rejects a second coach row for the same user', function () {
    $user = User::factory()->create(['role' => UserRole::Coach]);
    Coach::factory()->create(['user_id' => $user->id]);

    Coach::factory()->create(['user_id' => $user->id]);
})->throws(QueryException::class);
