<?php

namespace App\Console\Commands;

use App\Enums\StatutSeance;
use App\Models\Seance;
use Illuminate\Console\Command;

class CloturerSeancesCommand extends Command
{
    protected $signature = 'seances:cloturer';

    protected $description = 'Clôture automatiquement les séances en_cours ou a_venir dont la date est révolue (le coach a oublié de clôturer, ou la séance n\'a jamais démarré)';

    public function handle(): int
    {
        // en_cours: the coach forgot to clôturer — close it out now.
        // a_venir: the séance's day passed without ever starting (démarrer
        // only starts a_venir séances dated today, so a stale a_venir
        // séance can otherwise never leave that statut) — it never
        // happened, so close it too. Either way, Seance::clore() is the
        // correct terminal transition: for an a_venir séance nobody could
        // have pointed (it never opened), so every active fille correctly
        // ends up marked absent.
        $cloturees = Seance::whereIn('statut', [StatutSeance::EnCours, StatutSeance::AVenir])
            ->get()
            ->filter(fn (Seance $seance) => $seance->date->isPast() && ! $seance->date->isToday())
            ->each(fn (Seance $seance) => $seance->clore())
            ->count();

        $this->info("{$cloturees} séance(s) clôturée(s) automatiquement.");

        return self::SUCCESS;
    }
}
