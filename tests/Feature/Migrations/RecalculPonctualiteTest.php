<?php

use App\Models\Fille;
use App\Models\Seance;
use Illuminate\Support\Facades\DB;

function inserePointageBrut(Seance $seance, array $colonnes): int
{
    return DB::table('pointages')->insertGetId(array_merge([
        'seance_id' => $seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => Fille::factory()->create()->id,
        'source' => 'auto',
        'motif_correction' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $colonnes));
}

beforeEach(function () {
    $this->seance = Seance::factory()->create(['date' => '2026-09-22', 'heure_prevue' => '17:00:00']);
    $this->migration = require database_path('migrations/2026_09_26_000001_recalculer_ponctualite_tolerance_dix_minutes.php');
});

it('recalculates uncorrected pointages with the 10-minute tolerance', function () {
    $dansLaTolerance = inserePointageBrut($this->seance, ['pointe_a' => '2026-09-22 17:07:00', 'statut_ponctualite' => 'en_retard', 'minutes_retard' => 7]);
    $ancienRetardFort = inserePointageBrut($this->seance, ['pointe_a' => '2026-09-22 17:20:00', 'statut_ponctualite' => 'retard_fort', 'minutes_retard' => 20]);

    $this->migration->up();

    expect(DB::table('pointages')->find($dansLaTolerance))
        ->statut_ponctualite->toBe('a_l_heure')
        ->minutes_retard->toBe(0);
    expect(DB::table('pointages')->find($ancienRetardFort))
        ->statut_ponctualite->toBe('en_retard')
        ->minutes_retard->toBe(20);
});

it('keeps admin corrections, only renaming retard fort to en retard', function () {
    $corrigeRetardFort = inserePointageBrut($this->seance, ['pointe_a' => '2026-09-22 17:05:00', 'statut_ponctualite' => 'retard_fort', 'minutes_retard' => 25, 'motif_correction' => 'Vérifié sur la feuille de présence.']);
    $corrigeALHeure = inserePointageBrut($this->seance, ['pointe_a' => '2026-09-22 17:30:00', 'statut_ponctualite' => 'a_l_heure', 'minutes_retard' => 0, 'motif_correction' => 'Retard justifié par le coach.']);

    $this->migration->up();

    expect(DB::table('pointages')->find($corrigeRetardFort))
        ->statut_ponctualite->toBe('en_retard')
        ->minutes_retard->toBe(25);
    expect(DB::table('pointages')->find($corrigeALHeure))
        ->statut_ponctualite->toBe('a_l_heure')
        ->minutes_retard->toBe(0);
});

it('leaves absences untouched', function () {
    $absent = inserePointageBrut($this->seance, ['pointe_a' => null, 'statut_ponctualite' => 'absent', 'minutes_retard' => null, 'source' => 'coach']);

    $this->migration->up();

    expect(DB::table('pointages')->find($absent))
        ->statut_ponctualite->toBe('absent')
        ->minutes_retard->toBeNull();
});
