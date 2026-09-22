<?php

namespace Database\Factories;

use App\Models\Fille;
use Illuminate\Database\Eloquent\Factories\Factory;

class FilleFactory extends Factory
{
    protected $model = Fille::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName('female'),
            'contact' => fake()->optional()->phoneNumber(),
            'pin' => sprintf('%04d', fake()->unique()->randomNumber(4)),
            'statut' => 'actif',
            'date_entree' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
        ];
    }
}
