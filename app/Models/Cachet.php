<?php

namespace App\Models;

use App\Enums\StatutCachet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cachet extends Model
{
    use HasFactory;

    protected $fillable = [
        'prestation_id', 'fille_id', 'montant', 'statut',
        'declaree_at', 'validee_at', 'valide_par_user_id',
        'corrige_par_user_id', 'motif_correction',
        'depense_creee_at', 'caisse_cafab_reference', 'depense_erreur',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'statut' => StatutCachet::class,
            'declaree_at' => 'datetime',
            'validee_at' => 'datetime',
            'depense_creee_at' => 'datetime',
        ];
    }

    public function prestation(): BelongsTo
    {
        return $this->belongsTo(Prestation::class);
    }

    public function fille(): BelongsTo
    {
        return $this->belongsTo(Fille::class);
    }

    public function valideParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par_user_id');
    }

    public function corrigeParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrige_par_user_id');
    }

    public function estFinalise(): bool
    {
        return in_array($this->statut, [StatutCachet::ValideePayee, StatutCachet::Annule], true);
    }
}
