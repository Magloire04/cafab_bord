<?php

use App\Enums\UserRole;
use App\Models\User;

it('keeps only the indicators on the admin dashboard', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Coachs actifs')
        ->assertDontSee('link-tile', false);
});
