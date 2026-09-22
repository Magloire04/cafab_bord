<?php

namespace App\Models;

use App\Enums\SourcePointage;
use App\Enums\StatutPonctualite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Pointage extends Model
{
    use HasFactory;

    protected $fillable = [
        'seance_id', 'pointable_type', 'pointable_id', 'pointe_a',
        'statut_ponctualite', 'minutes_retard', 'source',
        'pointe_par_user_id', 'corrige_par_user_id', 'motif_correction',
    ];

    protected function casts(): array
    {
        return [
            'pointe_a' => 'datetime',
            'statut_ponctualite' => StatutPonctualite::class,
            'source' => SourcePointage::class,
        ];
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function pointable(): MorphTo
    {
        return $this->morphTo();
    }

    public function pointeParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pointe_par_user_id');
    }

    public function corrigeParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrige_par_user_id');
    }
}
