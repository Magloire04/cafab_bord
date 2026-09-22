<?php

use App\Enums\UserRole;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from the admin pointage history', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.pointages.index'))->assertForbidden();
});

it('lets the admin list all pointages', function () {
    Pointage::factory()->count(3)->create();

    $this->actingAs($this->admin)->get(route('admin.pointages.index'))->assertOk();
});

it('lets the admin filter pointages by fille', function () {
    $fille = Fille::factory()->create();
    Pointage::factory()->create(['pointable_type' => Fille::class, 'pointable_id' => $fille->id]);
    Pointage::factory()->create();

    $response = $this->actingAs($this->admin)->get(route('admin.pointages.index', [
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
    ]));

    $response->assertOk();
});

it('lets the admin correct a pointage with a mandatory motif', function () {
    $pointage = Pointage::factory()->create(['statut_ponctualite' => 'en_retard', 'minutes_retard' => 10]);

    $response = $this->actingAs($this->admin)->patch(route('admin.pointages.corriger', $pointage), [
        'statut_ponctualite' => 'a_l_heure',
        'motif' => 'Erreur de pointage, la fille est arrivée avant le début.',
    ]);

    $response->assertRedirect();
    $pointage->refresh();
    expect($pointage->statut_ponctualite->value)->toBe('a_l_heure');
    expect($pointage->corrige_par_user_id)->toBe($this->admin->id);
    expect($pointage->motif_correction)->not->toBeNull();
});

it('rejects a correction without a motif', function () {
    $pointage = Pointage::factory()->create();

    $response = $this->actingAs($this->admin)->patch(route('admin.pointages.corriger', $pointage), [
        'statut_ponctualite' => 'absent',
        'motif' => '',
    ]);

    $response->assertSessionHasErrors('motif');
});
