<?php

namespace App\Services;

use App\Exceptions\CaisseCafabException;
use App\Models\Cachet;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CaisseCafabClient
{
    public function creerDepense(Cachet $cachet): string
    {
        $reference = "cachet-{$cachet->id}";
        $url = rtrim((string) config('services.caisse_cafab.url'), '/').'/api/operations';

        try {
            $response = Http::withToken((string) config('services.caisse_cafab.token'))
                ->timeout(10)
                ->post($url, [
                    'montant' => (float) $cachet->montant,
                    'date_operation' => $cachet->prestation->date->toDateString(),
                    'motif' => "Cachet — {$cachet->prestation->titre} — {$cachet->fille->prenom} {$cachet->fille->nom}",
                    'categorie' => 'Prestations',
                    'external_reference' => $reference,
                ]);
        } catch (ConnectionException $e) {
            throw new CaisseCafabException('Connexion à Caisse CAFAB impossible.', previous: $e);
        }

        if ($response->failed()) {
            throw new CaisseCafabException("Caisse CAFAB a répondu avec une erreur ({$response->status()}).");
        }

        return $response->json('external_reference') ?? $reference;
    }
}
