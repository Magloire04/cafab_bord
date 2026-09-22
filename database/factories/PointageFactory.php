<?php

namespace Database\Factories;

use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use Illuminate\Database\Eloquent\Factories\Factory;

class PointageFactory extends Factory
{
    protected $model = Pointage::class;

    public function definition(): array
    {
        return [
            'seance_id' => Seance::factory(),
            'pointable_type' => Fille::class,
            'pointable_id' => Fille::factory(),
            'pointe_a' => now(),
            'statut_ponctualite' => 'a_l_heure',
            'minutes_retard' => 0,
            'source' => 'auto',
        ];
    }
}
