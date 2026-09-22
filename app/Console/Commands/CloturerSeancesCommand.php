<?php

namespace App\Console\Commands;

use App\Enums\StatutSeance;
use App\Models\Seance;
use Illuminate\Console\Command;

class CloturerSeancesCommand extends Command
{
    protected $signature = 'seances:cloturer';

    protected $description = 'Clôture automatiquement les séances en_cours dont la date est révolue (le coach a oublié de clôturer)';

    public function handle(): int
    {
        $cloturees = Seance::where('statut', StatutSeance::EnCours)
            ->get()
            ->filter(fn (Seance $seance) => $seance->date->isPast() && ! $seance->date->isToday())
            ->each(fn (Seance $seance) => $seance->clore())
            ->count();

        $this->info("{$cloturees} séance(s) clôturée(s) automatiquement.");

        return self::SUCCESS;
    }
}
