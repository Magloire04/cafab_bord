<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TOLERANCE_MINUTES = 10;

    public function up(): void
    {
        // Corrections de l'admin : on les respecte, seul l'ancien statut disparaît.
        DB::table('pointages')
            ->whereNotNull('motif_correction')
            ->where('statut_ponctualite', 'retard_fort')
            ->update(['statut_ponctualite' => 'en_retard']);

        // Pointages automatiques ou du coach : recalculés avec la tolérance de 10 minutes.
        DB::table('pointages')
            ->join('seances', 'seances.id', '=', 'pointages.seance_id')
            ->whereNull('pointages.motif_correction')
            ->whereNotNull('pointages.pointe_a')
            ->select('pointages.id', 'pointages.pointe_a', 'seances.date', 'seances.heure_prevue')
            ->orderBy('pointages.id')
            ->each(function (object $ligne) {
                $prevue = Carbon::parse(Carbon::parse($ligne->date)->format('Y-m-d').' '.$ligne->heure_prevue);
                $ecart = (int) floor((Carbon::parse($ligne->pointe_a)->getTimestamp() - $prevue->getTimestamp()) / 60);

                DB::table('pointages')->where('id', $ligne->id)->update(
                    $ecart <= self::TOLERANCE_MINUTES
                        ? ['statut_ponctualite' => 'a_l_heure', 'minutes_retard' => 0]
                        : ['statut_ponctualite' => 'en_retard', 'minutes_retard' => $ecart]
                );
            });
    }

    public function down(): void
    {
        // Les anciens statuts « retard fort » ne sont pas reconstruits (application hors production).
    }
};
