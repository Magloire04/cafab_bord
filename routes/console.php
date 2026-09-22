<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('seances:generer')->daily();
Schedule::command('seances:demarrer')->everyMinute()->withoutOverlapping();
Schedule::command('seances:cloturer')->everyMinute()->withoutOverlapping();
