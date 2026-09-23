<?php

namespace App\Services;

use App\Enums\StatutCachet;
use App\Exceptions\CachetException;
use App\Models\Cachet;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CachetService
{
    public function __construct(private readonly CaisseCafabClient $caisseCafabClient = new CaisseCafabClient) {}

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

        $this->declencherDepense($cachet);

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

    public function reessayerDepense(Cachet $cachet): Cachet
    {
        if ($cachet->statut !== StatutCachet::ValideePayee || $cachet->depense_creee_at !== null) {
            throw CachetException::nonEligible();
        }

        $this->declencherDepense($cachet);

        return $cachet->fresh();
    }

    private function declencherDepense(Cachet $cachet): void
    {
        try {
            $reference = $this->caisseCafabClient->creerDepense($cachet);

            $cachet->update([
                'depense_creee_at' => now(),
                'caisse_cafab_reference' => $reference,
                'depense_erreur' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Échec de la création de la dépense Caisse CAFAB pour le cachet.', [
                'cachet_id' => $cachet->id,
                'exception' => $e,
            ]);

            $cachet->update(['depense_erreur' => $e->getMessage()]);
        }
    }
}
