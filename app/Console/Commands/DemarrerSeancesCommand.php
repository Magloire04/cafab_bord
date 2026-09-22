<?php

namespace App\Console\Commands;

use App\Enums\StatutSeance;
use App\Models\Seance;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DemarrerSeancesCommand extends Command
{
    protected $signature = 'seances:demarrer';

    protected $description = 'Passe en "en_cours" les séances à venir dont l\'heure prévue est arrivée';

    public function handle(): int
    {
        $demarrees = Seance::where('statut', StatutSeance::AVenir)
            ->get()
            ->filter(fn (Seance $seance) => $seance->heurePrevueCarbon()->lessThanOrEqualTo(Carbon::now()))
            ->each(fn (Seance $seance) => $seance->update(['statut' => StatutSeance::EnCours]))
            ->count();

        $this->info("{$demarrees} séance(s) démarrée(s).");

        return self::SUCCESS;
    }
}
