<?php

namespace Database\Factories;

use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Prestation;
use Illuminate\Database\Eloquent\Factories\Factory;

class CachetFactory extends Factory
{
    protected $model = Cachet::class;

    public function definition(): array
    {
        return [
            'prestation_id' => Prestation::factory(),
            'fille_id' => Fille::factory(),
            'montant' => 5000,
            'statut' => 'du',
        ];
    }
}
