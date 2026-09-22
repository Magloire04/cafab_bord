<?php

namespace Database\Factories;

use App\Models\Coach;
use App\Models\Seance;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeanceFactory extends Factory
{
    protected $model = Seance::class;

    public function definition(): array
    {
        return [
            'planning_repetition_id' => null,
            'coach_id' => Coach::factory(),
            'date' => now()->toDateString(),
            'heure_prevue' => '17:00:00',
            'type' => 'extraordinaire',
            'statut' => 'a_venir',
        ];
    }
}
