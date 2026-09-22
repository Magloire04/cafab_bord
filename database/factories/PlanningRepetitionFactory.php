<?php

namespace Database\Factories;

use App\Models\Coach;
use App\Models\PlanningRepetition;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanningRepetitionFactory extends Factory
{
    protected $model = PlanningRepetition::class;

    public function definition(): array
    {
        return [
            'jour_semaine' => $this->faker->numberBetween(1, 7),
            'heure_debut' => '17:00:00',
            'coach_id' => Coach::factory(),
            'actif' => true,
        ];
    }
}
