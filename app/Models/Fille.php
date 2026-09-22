<?php

namespace App\Models;

use App\Enums\StatutPersonne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fille extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'prenom', 'contact', 'pin', 'statut', 'date_entree'];

    protected function casts(): array
    {
        return [
            'statut' => StatutPersonne::class,
            'date_entree' => 'date',
        ];
    }
}
