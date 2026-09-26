<?php

use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\User;

it('gives the coach Registre and Planning, without the admin sections', function () {
    $coach = Coach::factory()->create();

    $this->actingAs($coach->user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('filles.index'), false)
        ->assertSee(route('plannings.index'), false)
        ->assertDontSee(route('admin.prestations.index'), false);
});

it('shows the admin every Planning tab, including Séance extraordinaire', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('plannings.index'))
        ->assertOk()
        ->assertSee(route('admin.calendrier'), false)
        ->assertSee(route('admin.pointages.index'), false)
        ->assertSee(route('seances.create-extraordinaire'), false);
});

it('shows the coach only the Planning tabs he can use', function () {
    $coach = Coach::factory()->create();

    $this->actingAs($coach->user)->get(route('plannings.index'))
        ->assertOk()
        ->assertSee(route('seances.create-extraordinaire'), false)
        ->assertDontSee(route('admin.calendrier'), false);
});

it('shows the coach only the Filles tab of the Registre', function () {
    $coach = Coach::factory()->create();

    $this->actingAs($coach->user)->get(route('filles.index'))
        ->assertOk()
        ->assertDontSee(route('admin.coaches.index'), false);
});

it('shows the admin the Coachs and Filles tabs', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('admin.coaches.index'))
        ->assertOk()
        ->assertSee(route('filles.index'), false);
});
