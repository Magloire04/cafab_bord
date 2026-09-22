<?php

namespace App\Services;

use App\Enums\SourcePointage;
use App\Enums\StatutPonctualite;
use App\Enums\StatutSeance;
use App\Exceptions\PointageException;
use App\Models\Pointage;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PointageService
{
    public const SEUIL_RETARD_FORT_MINUTES = 15;

    public function pointer(
        Seance $seance,
        Model $personne,
        Carbon $heure,
        SourcePointage $source,
        ?User $parUser = null
    ): Pointage {
        if ($seance->statut !== StatutSeance::EnCours) {
            throw PointageException::seanceNonOuverte();
        }

        $dejaPointe = Pointage::where('seance_id', $seance->id)
            ->where('pointable_type', $personne::class)
            ->where('pointable_id', $personne->id)
            ->exists();

        if ($dejaPointe) {
            throw PointageException::dejaPointe();
        }

        [$statut, $minutesRetard] = $this->calculerPonctualite($seance->heurePrevueCarbon(), $heure);

        return Pointage::create([
            'seance_id' => $seance->id,
            'pointable_type' => $personne::class,
            'pointable_id' => $personne->id,
            'pointe_a' => $heure,
            'statut_ponctualite' => $statut,
            'minutes_retard' => $minutesRetard,
            'source' => $source,
            'pointe_par_user_id' => $parUser?->id,
        ]);
    }

    /**
     * @return array{0: StatutPonctualite, 1: int}
     */
    private function calculerPonctualite(Carbon $heurePrevue, Carbon $heureArrivee): array
    {
        $minutesEcart = (int) floor(($heureArrivee->getTimestamp() - $heurePrevue->getTimestamp()) / 60);

        if ($minutesEcart <= 0) {
            return [StatutPonctualite::ALHeure, 0];
        }

        if ($minutesEcart <= self::SEUIL_RETARD_FORT_MINUTES) {
            return [StatutPonctualite::EnRetard, $minutesEcart];
        }

        return [StatutPonctualite::RetardFort, $minutesEcart];
    }
}
