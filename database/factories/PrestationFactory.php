<?php

namespace Database\Factories;

use App\Models\Prestation;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrestationFactory extends Factory
{
    protected $model = Prestation::class;

    public function definition(): array
    {
        return [
            'titre' => $this->faker->sentence(3),
            'lieu' => $this->faker->city(),
            'date' => now()->addWeek()->toDateString(),
            'montant_defaut' => 5000,
            'statut' => 'active',
        ];
    }
}
