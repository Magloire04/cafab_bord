<?php

namespace App\Console\Commands;

use App\Services\SeanceCycleDeVie;
use Illuminate\Console\Command;

class CloturerSeancesCommand extends Command
{
    protected $signature = 'seances:cloturer';

    protected $description = 'Clôture automatiquement les séances en_cours ou a_venir dont la date est révolue (le coach a oublié de clôturer, ou la séance n\'a jamais démarré)';

    public function handle(SeanceCycleDeVie $cycleDeVie): int
    {
        $this->info("{$cycleDeVie->cloturer()} séance(s) clôturée(s) automatiquement.");

        return self::SUCCESS;
    }
}
