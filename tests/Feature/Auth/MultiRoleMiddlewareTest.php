<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['auth', 'role:admin,coach'])->get('/test/admin-or-coach', fn () => 'ok');
});

it('lets an admin through a multi-role gate', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/test/admin-or-coach')->assertOk();
});

it('lets a coach through the same multi-role gate', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get('/test/admin-or-coach')->assertOk();
});

it('still blocks a mismatched single role', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get('/admin/ping')->assertForbidden();
});
