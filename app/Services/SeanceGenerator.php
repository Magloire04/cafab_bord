<?php

namespace App\Services;

use App\Enums\TypeSeance;
use App\Models\PlanningRepetition;
use App\Models\Seance;
use Illuminate\Support\Carbon;

class SeanceGenerator
{
    public function genererPourLesProchainsJours(int $jours = 14): int
    {
        $plannings = PlanningRepetition::where('actif', true)->get();
        $created = 0;

        foreach ($plannings as $planning) {
            for ($i = 0; $i < $jours; $i++) {
                $date = Carbon::now()->addDays($i);

                if ($date->isoWeekday() !== $planning->jour_semaine->value) {
                    continue;
                }

                // Un créneau créé aujourd'hui après son heure ne doit pas produire de
                // séance du jour : elle serait clôturée demain avec toutes les filles absentes.
                if ($date->isToday() && Carbon::parse($date->toDateString().' '.$planning->heure_debut)->isPast()) {
                    continue;
                }

                $existe = Seance::where('planning_repetition_id', $planning->id)
                    ->whereDate('date', $date->toDateString())
                    ->exists();

                if ($existe) {
                    continue;
                }

                Seance::create([
                    'planning_repetition_id' => $planning->id,
                    'coach_id' => $planning->coach_id,
                    'date' => $date->toDateString(),
                    'heure_prevue' => $planning->heure_debut,
                    'type' => TypeSeance::Recurrente,
                    'statut' => 'a_venir',
                ]);
                $created++;
            }
        }

        return $created;
    }
}
