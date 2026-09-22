<?php

namespace App\Models;

use App\Enums\StatutSeance;
use App\Enums\TypeSeance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Seance extends Model
{
    use HasFactory;

    protected $fillable = [
        'planning_repetition_id', 'coach_id', 'date', 'heure_prevue',
        'type', 'statut', 'cloturee_at', 'cloture_par_user_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => TypeSeance::class,
            'statut' => StatutSeance::class,
            'cloturee_at' => 'datetime',
        ];
    }

    public function planningRepetition(): BelongsTo
    {
        return $this->belongsTo(PlanningRepetition::class, 'planning_repetition_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function clotureParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cloture_par_user_id');
    }

    public function pointages(): HasMany
    {
        return $this->hasMany(Pointage::class);
    }

    public function heurePrevueCarbon(): Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d').' '.$this->heure_prevue);
    }

    public function estEnCours(): bool
    {
        return $this->statut === StatutSeance::EnCours;
    }
}
