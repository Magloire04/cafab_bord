<?php

namespace App\Services;

use App\Enums\StatutPonctualite;
use App\Models\Coach;
use App\Models\Fille;
use App\Models\Pointage;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

class PonctualiteRapportService
{
    public function generer(?string $dateDebut, ?string $dateFin, ?int $filleId, ?int $coachId): Collection
    {
        // A plain `with('pointable.user')` breaks here: `pointable` is polymorphic
        // (Fille or Coach) and only Coach defines a `user` relation — Eloquent
        // throws trying to eager-load `user` on the Fille rows. `morphWith()`
        // scopes the nested eager load to the one morph type that has it.
        $pointages = Pointage::entrePeriode($dateDebut, $dateFin)
            ->pourFille($filleId)
            ->pourCoach($coachId)
            ->with(['pointable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([Coach::class => ['user']]);
            }])
            ->get();

        return $pointages
            ->groupBy(fn (Pointage $p) => $p->pointable_type.'|'.$p->pointable_id)
            ->map(function (Collection $groupe) {
                $premier = $groupe->first();
                $estFille = $premier->pointable_type === Fille::class;

                $nom = $estFille
                    ? "{$premier->pointable->prenom} {$premier->pointable->nom}"
                    : $premier->pointable->user->name;

                $presences = $groupe->where('statut_ponctualite', StatutPonctualite::ALHeure)->count();
                $enRetard = $groupe->whereIn('statut_ponctualite', [StatutPonctualite::EnRetard, StatutPonctualite::RetardFort]);
                $absences = $groupe->where('statut_ponctualite', StatutPonctualite::Absent)->count();
                $total = $groupe->count();

                return [
                    'type' => $estFille ? 'Fille' : 'Coach',
                    'nom' => $nom,
                    'presences' => $presences,
                    'retards' => $enRetard->count(),
                    'retard_cumule' => (int) $enRetard->sum('minutes_retard'),
                    'absences' => $absences,
                    'taux_presence' => $total > 0 ? round((($presences + $enRetard->count()) / $total) * 100, 1) : 0.0,
                ];
            })
            ->values();
    }
}
