<?php

namespace App\Services;

use App\Enums\StatutCachet;
use App\Exceptions\CachetException;
use App\Models\Cachet;

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
}
