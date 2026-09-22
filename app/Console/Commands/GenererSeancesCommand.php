<?php

namespace App\Console\Commands;

use App\Services\SeanceGenerator;
use Illuminate\Console\Command;

class GenererSeancesCommand extends Command
{
    protected $signature = 'seances:generer {--jours=14}';

    protected $description = 'Génère les séances à venir à partir du planning récurrent actif';

    public function handle(SeanceGenerator $generator): int
    {
        $created = $generator->genererPourLesProchainsJours((int) $this->option('jours'));

        $this->info("{$created} séance(s) générée(s).");

        return self::SUCCESS;
    }
}
