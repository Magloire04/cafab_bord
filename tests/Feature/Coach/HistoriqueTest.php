<?php

use App\Models\Coach;
use App\Models\Fille;
use App\Models\Pointage;

it('shows a coach only their own pointage history', function () {
    $coach = Coach::factory()->create();
    Pointage::factory()->create(['pointable_type' => Coach::class, 'pointable_id' => $coach->id]);
    Pointage::factory()->create(['pointable_type' => Fille::class]);

    $response = $this->actingAs($coach->user)->get(route('coach.historique'));

    $response->assertOk();
});
