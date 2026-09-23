<?php

namespace App\Services;

use App\Enums\StatutCachet;
use App\Exceptions\CachetException;
use App\Models\Cachet;
use App\Models\User;

class CachetService
{
    public function declarer(Cachet $cachet, bool $recu): Cachet
    {
        if ($cachet->estFinalise()) {
            throw CachetException::nonEligible();
        }

        if (! $cachet->prestation->estPassee()) {
            throw CachetException::prestationNonEncorePassee();
        }

        $cachet->update([
            'statut' => $recu ? StatutCachet::DeclareePayee : StatutCachet::DeclareeNonPayee,
            'declaree_at' => now(),
        ]);

        return $cachet->fresh();
    }

    public function valider(Cachet $cachet, User $admin): Cachet
    {
        if ($cachet->estFinalise()) {
            throw CachetException::nonEligible();
        }

        $cachet->update([
            'statut' => StatutCachet::ValideePayee,
            'validee_at' => now(),
            'valide_par_user_id' => $admin->id,
        ]);

        return $cachet->fresh();
    }

    public function corriger(Cachet $cachet, StatutCachet $statut, string $motif, User $admin): Cachet
    {
        if ($cachet->estFinalise()) {
            throw CachetException::nonEligible();
        }

        $cachet->update([
            'statut' => $statut,
            'corrige_par_user_id' => $admin->id,
            'motif_correction' => $motif,
        ]);

        return $cachet->fresh();
    }

    public function ajusterMontant(Cachet $cachet, float $montant): Cachet
    {
        if ($cachet->estFinalise()) {
            throw CachetException::nonEligible();
        }

        $cachet->update(['montant' => $montant]);

        return $cachet->fresh();
    }
}
