<?php

namespace App\Models;

use App\Enums\StatutPrestation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Prestation extends Model
{
    use HasFactory;

    protected $fillable = ['titre', 'lieu', 'date', 'montant_defaut', 'statut'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'montant_defaut' => 'decimal:2',
            'statut' => StatutPrestation::class,
        ];
    }

    public function cachets(): HasMany
    {
        return $this->hasMany(Cachet::class);
    }

    public function estPassee(): bool
    {
        return $this->date->lt(Carbon::today());
    }
}
