<?php

namespace App\Models;

use App\Enums\JourSemaine;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PlanningRepetition extends Model
{
    use HasFactory;

    protected $table = 'plannings_repetition';

    protected $fillable = ['jour_semaine', 'heure_debut', 'coach_id', 'actif'];

    protected function casts(): array
    {
        return [
            'jour_semaine' => JourSemaine::class,
            'actif' => 'boolean',
        ];
    }

    protected function heureDebut(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Carbon::parse($value)->format('H:i:s'),
        );
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class, 'planning_repetition_id');
    }
}
