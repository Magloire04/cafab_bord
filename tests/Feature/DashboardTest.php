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

it('addresses the coach formally on his dashboard', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Consultez vos séances passées et votre ponctualité.')
        ->assertDontSee('Consulte tes');
});
