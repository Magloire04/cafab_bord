<?php

use App\Enums\UserRole;
use App\Models\User;

it('lets an admin reach an admin-only route', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->get('/admin/ping');

    $response->assertOk();
});

it('blocks a coach from an admin-only route', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $response = $this->actingAs($coach)->get('/admin/ping');

    $response->assertForbidden();
});

it('blocks a guest from an admin-only route', function () {
    $response = $this->get('/admin/ping');

    $response->assertRedirect('/login');
});
