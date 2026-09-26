<?php

namespace App\Console\Commands;

use App\Services\SeanceCycleDeVie;
use Illuminate\Console\Command;

class DemarrerSeancesCommand extends Command
{
    protected $signature = 'seances:demarrer';

    protected $description = 'Passe en "en_cours" les séances à venir dont l\'heure prévue est arrivée';

    public function handle(SeanceCycleDeVie $cycleDeVie): int
    {
        $this->info("{$cycleDeVie->demarrer()} séance(s) démarrée(s).");

        return self::SUCCESS;
    }
}
