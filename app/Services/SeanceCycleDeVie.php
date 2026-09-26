<?php

namespace App\Services;

use App\Enums\StatutSeance;
use App\Models\Seance;
use Illuminate\Support\Carbon;

/**
 * Seul point de passage des changements d'état des séances : le
 * planificateur, le middleware SynchroniserSeances et les écrans de
 * planning l'appellent, pour que l'application reste à jour même quand
 * `schedule:work` ne tourne pas.
 */
class SeanceCycleDeVie
{
    public function __construct(private SeanceGenerator $generateur) {}

    public function synchroniser(): void
    {
        $this->generer();
        $this->demarrer();
        $this->cloturer();
    }

    public function generer(int $jours = 14): int
    {
        return $this->generateur->genererPourLesProchainsJours($jours);
    }

    public function demarrer(): int
    {
        return Seance::where('statut', StatutSeance::AVenir)
            ->whereDate('date', today())
            ->get()
            ->filter(fn (Seance $seance) => $seance->heurePrevueCarbon()->lessThanOrEqualTo(Carbon::now()))
            ->each(fn (Seance $seance) => $seance->update(['statut' => StatutSeance::EnCours]))
            ->count();
    }

    /**
     * En cours : le coach a oublié de clôturer. À venir : la date est passée
     * sans que la séance démarre. Dans les deux cas Seance::clore() est la
     * bonne transition finale (elle matérialise les absences).
     */
    public function cloturer(): int
    {
        return Seance::whereIn('statut', [StatutSeance::EnCours, StatutSeance::AVenir])
            ->whereDate('date', '<', today())
            ->get()
            ->each(fn (Seance $seance) => $seance->clore())
            ->count();
    }
}
