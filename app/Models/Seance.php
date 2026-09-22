<?php

namespace App\Models;

use App\Enums\SourcePointage;
use App\Enums\StatutPonctualite;
use App\Enums\StatutSeance;
use App\Enums\TypeSeance;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Seance extends Model
{
    use HasFactory;

    protected $fillable = [
        'planning_repetition_id', 'coach_id', 'date', 'heure_prevue',
        'type', 'statut', 'cloturee_at', 'cloture_par_user_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => TypeSeance::class,
            'statut' => StatutSeance::class,
            'cloturee_at' => 'datetime',
        ];
    }

    public function planningRepetition(): BelongsTo
    {
        return $this->belongsTo(PlanningRepetition::class, 'planning_repetition_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function clotureParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cloture_par_user_id');
    }

    public function pointages(): HasMany
    {
        return $this->hasMany(Pointage::class);
    }

    protected function heurePrevue(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Carbon::parse($value)->format('H:i:s'),
        );
    }

    public function heurePrevueCarbon(): Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d').' '.$this->heure_prevue);
    }

    public function estEnCours(): bool
    {
        return $this->statut === StatutSeance::EnCours;
    }

    /**
     * Clôture the séance and materializes an "absent" pointage for every
     * active fille who doesn't already have one for this séance.
     *
     * This is the single shared clôture path: both the coach's manual
     * "Clôturer" button and the seances:cloturer auto-clôture command call
     * it, so the absence-materialization logic below only has to be
     * written once.
     *
     * The Pointage rows below are created directly via Pointage::create(),
     * not via PointageService::pointer() — deliberately. pointer()'s whole
     * contract is "record an arrival with a punctuality calculation", which
     * doesn't apply here: there was no arrival. Marking someone absent is a
     * side effect of closing the séance, not a pointage-recording
     * operation, so it's a narrow, intentional exception to the rule that
     * PointageService is the only pointage-creating path.
     */
    public function clore(?User $parUser = null): void
    {
        DB::transaction(function () use ($parUser) {
            $this->update([
                'statut' => StatutSeance::Cloturee,
                'cloturee_at' => now(),
                'cloture_par_user_id' => $parUser?->id,
            ]);

            $dejaPointees = $this->fillesDejaPointees();

            Fille::where('statut', 'actif')
                ->whereNotIn('id', $dejaPointees)
                ->get()
                ->each(function (Fille $fille) {
                    try {
                        Pointage::create([
                            'seance_id' => $this->id,
                            'pointable_type' => Fille::class,
                            'pointable_id' => $fille->id,
                            'pointe_a' => null,
                            'statut_ponctualite' => StatutPonctualite::Absent,
                            'minutes_retard' => null,
                            'source' => SourcePointage::Coach,
                        ]);
                    } catch (QueryException $e) {
                        // Backstop for a race between the pluck() above and
                        // this insert: a latecomer self-pointing (or being
                        // marked present) in the same instant as the
                        // clôture. The DB-level unique constraint on
                        // (seance, pointable) rejects our insert — that's
                        // correct, not an error: someone else's real
                        // pointage won the race, so this fille should not
                        // be marked absent. Skip her and let the clôture
                        // continue rather than aborting the whole
                        // transaction, mirroring PointageService::pointer()'s
                        // handling of the same race.
                    }
                });
        });
    }

    /**
     * Pre-check used to skip filles who already have a pointage before
     * attempting each insert. Extracted as its own method (rather than
     * inlined) so tests can simulate the race window where a fille's real
     * pointage is recorded after this check runs but before the
     * corresponding insert in clore() — the DB-level unique constraint is
     * the real backstop for that case, exercised via the try/catch there.
     */
    protected function fillesDejaPointees(): Collection
    {
        return $this->pointages()
            ->where('pointable_type', Fille::class)
            ->pluck('pointable_id');
    }
}
