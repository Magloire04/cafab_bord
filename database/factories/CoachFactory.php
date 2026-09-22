<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CoachFactory extends Factory
{
    protected $model = Coach::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => UserRole::Coach]),
            'pin' => sprintf('%04d', fake()->unique()->randomNumber(4)),
            'contact' => fake()->optional()->phoneNumber(),
            'statut' => 'actif',
            'date_entree' => fake()->date('Y-m-d', '-2 years'),
        ];
    }
}
