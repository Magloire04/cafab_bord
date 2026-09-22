<?php

namespace App\Services;

use App\Enums\SourcePointage;
use App\Enums\StatutPonctualite;
use App\Exceptions\PointageException;
use App\Models\Pointage;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
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
        if (! $seance->estEnCours()) {
            throw PointageException::seanceNonOuverte();
        }

        if ($this->existeDejaPointage($seance, $personne)) {
            throw PointageException::dejaPointe();
        }

        [$statut, $minutesRetard] = $this->calculerPonctualite($seance->heurePrevueCarbon(), $heure);

        try {
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
        } catch (QueryException $e) {
            // Backstop for a race between the exists() pre-check above and this
            // insert: the DB-level unique constraint (seance, pointable) rejects
            // the losing concurrent insert. Surface it as the same friendly
            // exception every other duplicate-pointage path returns.
            throw PointageException::dejaPointe();
        }
    }

    /**
     * Pre-check used to reject the common sequential case with a clear
     * exception before attempting the insert. Extracted as its own method
     * (rather than inlined) so tests can simulate the race window where two
     * requests both pass this check before either commits — the DB-level
     * unique constraint is the real backstop for that case, exercised via
     * the try/catch in pointer().
     */
    protected function existeDejaPointage(Seance $seance, Model $personne): bool
    {
        return Pointage::where('seance_id', $seance->id)
            ->where('pointable_type', $personne::class)
            ->where('pointable_id', $personne->id)
            ->exists();
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
