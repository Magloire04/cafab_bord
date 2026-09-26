# Corrections du 26 septembre Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply the 19 corrections Elisée listed after his first hands-on test of Présence & Paiements CAFAB: fix broken or unreachable features, change the lateness rule, give coaches rights on filles and on their own créneaux, add a provisional-password flow, make séances live without the scheduler, and finish several screens.

**Architecture:** Séance state changes move into one `SeanceCycleDeVie` service that the scheduler, a once-a-minute web middleware and créneau edits all call, so nothing depends on `schedule:work` running. A read-only `EtatSeances` service feeds a JSON endpoint polled by a banner (admin/coach) and by the kiosk. Account security gets two small services, `GenerateurMotDePasse` and `ChangementMotDePasse`, used by every password path, plus a middleware that blocks accounts flagged `must_change_password`. Coach rights are enforced by Laravel policies on pages now shared by both roles.

**Tech Stack:** Laravel 12, PHP 8.3+, Blade, Alpine.js, Bootstrap 5 (layout utilities, dropdown and modal JS only), Pest, Laravel Pint, SQLite in tests.

**Spec:** `docs/superpowers/specs/2026-09-26-corrections-design.md` (approved by Elisée on 2026-09-26). Source list: `C:\wamp64\www\caisse_cafab\documentations\presence-paiement-cafab\corrections.txt`. Visual reference: `C:\wamp64\www\caisse_cafab\documentations\maquette-cafab\DESIGN.md` and `ecrans\*.png`.

## Global Constraints

- PHP 8.3+, Laravel 12, Pest. Verify with `php artisan test` and `vendor/bin/pint --dirty` before every commit.
- No new Composer or npm dependency.
- Content-Security-Policy (`app/Http/Middleware/SecurityHeaders.php`: `style-src 'self'`, `script-src 'self' 'unsafe-eval'`): never write a `style="..."` attribute or an inline `<script>` block. JavaScript lives in `resources/js/*.js` modules imported from `resources/js/app.js`. Alpine directives are allowed. Setting `element.hidden` or classes from JS is fine.
- Visual vocabulary comes from `resources/css/_cafab-tokens.scss` and `resources/css/_cafab-app.scss`. New CSS is appended to `_cafab-app.scss`. Statuses are always rendered through `<x-badge-ponctualite>` / `<x-badge-cachet>`.
- UI copy is French. Admin and coach screens address the user as "vous"; kiosk screens (used by the filles) use "tu".
- Time is server time in `config('app.timezone')` (`Africa/Porto-Novo`). Tests freeze time with `Carbon::setTestNow(...)` and reset it with `Carbon::setTestNow()`.
- `Pointage.pointable_type` stores the literal class name (`App\Models\Fille::class`), never a morph-map alias.
- Lateness tolerance: 10 minutes, single constant `PointageService::TOLERANCE_MINUTES`. Up to 10 minutes included: "À l'heure", `minutes_retard = 0`. Beyond: "En retard", minutes counted from the scheduled start (12:19 for 12:06 gives 13).
- Password rules: a password chosen by a user follows `Password::defaults()` (8 characters minimum, lower- and upper-case letters, digits). A provisional password follows `GenerateurMotDePasse::regle()` (10 characters minimum, lower- and upper-case letters, digits, symbols).
- Every email entering the app (login, forgot password, profile, coach create and edit, artisan commands) is trimmed and lower-cased before validation and lookup.
- Money in new or rewritten views: `number_format((float) $montant, 0, ',', ' ').' F'`. Do not touch the existing `number_format(..., 2, ',', ' ')` calls in `admin/paiements` and `admin/rapports`.
- Never run `migrate`, `migrate:fresh` or `db:seed` from the worktree against a real database. Tests run on in-memory SQLite (`phpunit.xml`).
- `npm install` in the worktree rewrites the `"name"` field of `package-lock.json`. Never commit that change: `git checkout -- package-lock.json` before committing.
- Commit messages end with a blank line then `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. An email typed with capitals or surrounding spaces (login, forgot password, profile, coach create/edit) must be treated as the same lower-case address, and uniqueness checked on that normalized value. Tests: Task 5 (login, forgot password), Task 7 (profile), Task 9 (coach create and edit).
2. A coach-role account without a `Coach` profile (created with `php artisan users:create ... --role=coach`) opening the banner, Planning or Registre, or submitting a créneau, must get an empty state or a 403, never a 500. Tests: Task 3 (banner), Task 8 (créneau refused).
3. A créneau created today for an hour already past must not produce a séance today (it would only generate false absences at the next clôture); a créneau for an hour still to come today must produce today's séance, à venir. Tests: Task 2.
4. Banner and kiosk polling must survive the server answering with an error, a redirect (expired session, pending password change) or no answer: keep the last known state, never throw, never notify twice, and the kiosk must never reload while a fille is typing her code. Tests: Task 3 (401 JSON for guests), Task 6 (403 JSON while a change is pending); the JS guards are spelled out in Task 3.
5. A user flagged `must_change_password` who comes back through the remember-me cookie (new browser session) must still land on the change screen, and must still be able to log out. Tests: Task 6.

## File map

| Area | Files |
|---|---|
| Ponctualité | `app/Enums/StatutPonctualite.php`, `app/Services/PointageService.php`, migration `2026_09_26_000001_recalculer_ponctualite_tolerance_dix_minutes.php` |
| Cycle de vie | `app/Services/SeanceCycleDeVie.php`, `app/Http/Middleware/SynchroniserSeances.php`, `config/seances.php`, `app/Services/SeanceGenerator.php` |
| État des séances | `app/Services/EtatSeances.php`, `app/Http/Controllers/EtatSeancesController.php`, `app/Http/Controllers/Kiosque/EtatController.php`, `resources/views/components/bandeau-seance.blade.php`, `resources/js/bandeau-seance.js`, `resources/js/kiosque-etat.js` |
| Horloge | `resources/views/components/horloge.blade.php`, `resources/js/horloge.js` |
| Connexion | `lang/fr/*.php`, `lang/fr.json`, `resources/views/components/champ-mot-de-passe.blade.php`, `resources/js/password-toggle.js` |
| Mot de passe provisoire | `app/Services/GenerateurMotDePasse.php`, `app/Services/ChangementMotDePasse.php`, `app/Http/Middleware/ExigerChangementMotDePasse.php`, `app/Http/Controllers/Auth/ChangementObligatoireController.php`, `app/Console/Commands/ResetPasswordCommand.php` |
| Droits du coach | `app/Policies/FillePolicy.php`, `app/Policies/PlanningRepetitionPolicy.php`, `app/Http/Controllers/Registre/*`, `app/Http/Controllers/PlanningController.php`, `resources/views/components/onglets*.blade.php` |
| Comptes coachs, menus | `app/Http/Controllers/Admin/CoachController.php`, `resources/views/components/menu-actions.blade.php`, `resources/js/confirmation.js`, `resources/js/copier.js`, `resources/js/mot-de-passe-provisoire.js` |
| Prestation, dashboard | `app/Http/Controllers/Admin/PrestationController.php`, `resources/views/admin/prestations/show.blade.php`, `resources/views/dashboard.blade.php` |

---

## Task 1: Ponctualité à deux statuts, tolérance de 10 minutes (#14, #17)

**Files:**
- Modify: `app/Enums/StatutPonctualite.php`
- Modify: `app/Services/PointageService.php:17` and `:74-88`
- Create: `database/migrations/2026_09_26_000001_recalculer_ponctualite_tolerance_dix_minutes.php`
- Modify: `app/Http/Controllers/Admin/PointageController.php` (`corriger()` match)
- Modify: `app/Services/PonctualiteRapportService.php:39`
- Modify: `resources/views/admin/pointages/index.blade.php` (correction `<select>`)
- Modify: `resources/views/coach/historique.blade.php`, `resources/views/coach/seance.blade.php` (the `in_array(..., ['en_retard', 'retard_fort'])` calls)
- Modify: `resources/views/components/badge-ponctualite.blade.php`
- Test: `tests/Unit/Services/PointageServiceTest.php`, `tests/Feature/Admin/PointageHistoriqueTest.php`, create `tests/Feature/Migrations/RecalculPonctualiteTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `StatutPonctualite` has exactly three cases, `ALHeure`, `EnRetard`, `Absent`. `PointageService::TOLERANCE_MINUTES = 10` (public const). `SEUIL_RETARD_FORT_MINUTES` no longer exists.

- [ ] **Step 1: Rewrite the lateness tests**

In `tests/Unit/Services/PointageServiceTest.php`, delete these four tests entirely: `marks en retard between 1 and 15 minutes late`, `marks retard fort beyond 15 minutes late`, `treats exactly 15 minutes late as en retard, not retard fort`, `treats exactly 16 minutes late as retard fort`. Insert in their place:

```php
it('keeps a pointage à l’heure within the 10-minute tolerance', function () {
    $pointage = $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 17:10:00'), SourcePointage::Auto);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::ALHeure);
    expect($pointage->minutes_retard)->toBe(0);
});

it('ignores the seconds of the tenth minute', function () {
    $pointage = $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 17:10:59'), SourcePointage::Auto);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::ALHeure);
    expect($pointage->minutes_retard)->toBe(0);
});

it('marks en retard beyond the tolerance, counting minutes from the scheduled start', function () {
    $pointage = $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 17:11:00'), SourcePointage::Auto);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::EnRetard);
    expect($pointage->minutes_retard)->toBe(11);
});

it('marks 12:07 à l’heure for a séance scheduled at 12:06', function () {
    $seance = Seance::factory()->create(['date' => '2026-09-26', 'heure_prevue' => '12:06:00', 'statut' => StatutSeance::EnCours]);

    $pointage = $this->service->pointer($seance, $this->fille, Carbon::parse('2026-09-26 12:07:00'), SourcePointage::Coach);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::ALHeure);
});

it('reports 13 minutes late for 12:19 on a séance scheduled at 12:06', function () {
    $seance = Seance::factory()->create(['date' => '2026-09-26', 'heure_prevue' => '12:06:00', 'statut' => StatutSeance::EnCours]);

    $pointage = $this->service->pointer($seance, $this->fille, Carbon::parse('2026-09-26 12:19:00'), SourcePointage::Coach);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::EnRetard);
    expect($pointage->minutes_retard)->toBe(13);
});
```

In the same file, in `lets a coach record a pointage at a specific backdated time, not just now`, replace `->toBe(StatutPonctualite::EnRetard)` with `->toBe(StatutPonctualite::ALHeure)` (17:02 is now within the tolerance).

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test tests/Unit/Services/PointageServiceTest.php`
Expected: FAIL on the 17:10, 17:10:59, 12:07 and backdated tests (they still come out "en retard").

- [ ] **Step 3: Change the enum and the rule**

`app/Enums/StatutPonctualite.php`:

```php
<?php

namespace App\Enums;

enum StatutPonctualite: string
{
    case ALHeure = 'a_l_heure';
    case EnRetard = 'en_retard';
    case Absent = 'absent';
}
```

In `app/Services/PointageService.php`, replace `public const SEUIL_RETARD_FORT_MINUTES = 15;` with:

```php
    /**
     * Minutes après l'heure prévue pendant lesquelles une arrivée compte encore « à l'heure ».
     */
    public const TOLERANCE_MINUTES = 10;
```

and replace the body of `calculerPonctualite()` with:

```php
    private function calculerPonctualite(Carbon $heurePrevue, Carbon $heureArrivee): array
    {
        $minutesEcart = (int) floor(($heureArrivee->getTimestamp() - $heurePrevue->getTimestamp()) / 60);

        if ($minutesEcart <= self::TOLERANCE_MINUTES) {
            return [StatutPonctualite::ALHeure, 0];
        }

        return [StatutPonctualite::EnRetard, $minutesEcart];
    }
```

- [ ] **Step 4: Run the tests to see them pass**

Run: `php artisan test tests/Unit/Services/PointageServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Write the data-migration test**

Create `tests/Feature/Migrations/RecalculPonctualiteTest.php`. It reads and writes raw rows with `DB::table()`, because the `retard_fort` value can no longer be cast to the enum.

```php
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
```

- [ ] **Step 6: Run it to see it fail**

Run: `php artisan test tests/Feature/Migrations/RecalculPonctualiteTest.php`
Expected: FAIL, the migration file does not exist.

- [ ] **Step 7: Write the migration**

Create `database/migrations/2026_09_26_000001_recalculer_ponctualite_tolerance_dix_minutes.php`. The rule is copied inline on purpose: a migration must keep producing the same result even if `PointageService` changes later.

```php
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
```

- [ ] **Step 8: Run it to see it pass**

Run: `php artisan test tests/Feature/Migrations/RecalculPonctualiteTest.php`
Expected: PASS.

- [ ] **Step 9: Align every consumer of the old status**

`app/Http/Controllers/Admin/PointageController.php`, in `corriger()`, replace the `match` with:

```php
        $minutesRetard = match ($statut) {
            StatutPonctualite::ALHeure, StatutPonctualite::Absent => 0,
            StatutPonctualite::EnRetard => $request->filled('minutes_retard')
                ? $request->validated('minutes_retard')
                : $pointage->minutes_retard,
        };
```

`app/Services/PonctualiteRapportService.php:39`:

```php
                $enRetard = $groupe->where('statut_ponctualite', StatutPonctualite::EnRetard);
```

`resources/views/admin/pointages/index.blade.php`: delete the line `<option value="retard_fort">Retard fort</option>`.

`resources/views/coach/historique.blade.php` and `resources/views/coach/seance.blade.php`: replace `['en_retard', 'retard_fort']` with `['en_retard']` in both `in_array` calls.

`resources/views/components/badge-ponctualite.blade.php`: delete the line `'retard_fort' => ['class' => 'st-fort', 'label' => 'Retard fort'],`.

`tests/Feature/Admin/PointageHistoriqueTest.php:54`: replace `'statut_ponctualite' => 'retard_fort'` with `'statut_ponctualite' => 'en_retard'`. At the end of that file add:

```php
it('no longer offers the retard fort status in the correction form', function () {
    Pointage::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.pointages.index'))
        ->assertOk()
        ->assertDontSee('Retard fort');
});
```

- [ ] **Step 10: Check nothing references the old status**

Run: `grep -rn "RetardFort\|retard_fort\|SEUIL_RETARD" app resources tests database/factories routes`
Expected: matches only in `tests/Feature/Migrations/RecalculPonctualiteTest.php` (the raw fixture values). The migration itself is under `database/migrations`, outside this search.

- [ ] **Step 11: Full suite, Pint, commit**

Run: `php artisan test` (expected: all green) then `vendor/bin/pint --dirty`.

```bash
git checkout -- package-lock.json 2>/dev/null; git add -A
git commit -m "$(cat <<'EOF'
feat(ponctualite): single late status with a 10-minute tolerance

Drops "retard fort": arrivals up to 10 minutes after the scheduled
start are on time, later ones are late with minutes counted from the
start. A data migration recalculates existing pointages, keeping the
admin's manual corrections.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Cycle de vie des séances sans planificateur (#9, #12)

**Files:**
- Create: `app/Services/SeanceCycleDeVie.php`
- Create: `app/Http/Middleware/SynchroniserSeances.php`
- Create: `config/seances.php`
- Modify: `bootstrap/app.php`
- Modify: `phpunit.xml`, `.env.example`
- Modify: `app/Services/SeanceGenerator.php`
- Modify: `app/Console/Commands/DemarrerSeancesCommand.php`, `app/Console/Commands/CloturerSeancesCommand.php`
- Modify: `app/Http/Controllers/Admin/PlanningController.php` (`store()` and `reconcilerSeancesFutures()`)
- Modify: `app/Http/Controllers/Coach/PointageController.php` (`show()`)
- Test: create `tests/Unit/Services/SeanceCycleDeVieTest.php`, create `tests/Feature/SynchroniserSeancesTest.php`, modify `tests/Unit/Services/SeanceGeneratorTest.php`, `tests/Feature/Admin/PlanningManagementTest.php`, `tests/Feature/Coach/PointageGroupeTest.php`

**Interfaces:**
- Consumes: `SeanceGenerator::genererPourLesProchainsJours(int $jours = 14): int`, `Seance::clore(?User $parUser = null): void`.
- Produces:
  - `App\Services\SeanceCycleDeVie` with `synchroniser(): void`, `generer(int $jours = 14): int`, `demarrer(): int`, `cloturer(): int`.
  - `App\Http\Middleware\SynchroniserSeances`, appended to the `web` middleware group.
  - `config('seances.synchronisation_auto')` (bool, `true` by default, `false` in `phpunit.xml`).
  - `SeanceGenerator` never creates today's séance once its hour has passed.
  - `phpunit.xml` pins `APP_LOCALE=fr` (later tasks assert French dates).

- [ ] **Step 1: Write the service tests**

Create `tests/Unit/Services/SeanceCycleDeVieTest.php`:

```php
<?php

use App\Enums\StatutSeance;
use App\Models\PlanningRepetition;
use App\Models\Seance;
use App\Services\SeanceCycleDeVie;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

it('generates, starts and closes séances in one pass', function () {
    Carbon::setTestNow('2026-09-22 17:05:00'); // mardi

    $jeudi = PlanningRepetition::factory()->create(['jour_semaine' => 4, 'heure_debut' => '17:00:00', 'actif' => true]);
    $aDemarrer = Seance::factory()->create(['date' => '2026-09-22', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);
    $oubliee = Seance::factory()->create(['date' => '2026-09-21', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::EnCours]);

    app(SeanceCycleDeVie::class)->synchroniser();

    expect(Seance::where('planning_repetition_id', $jeudi->id)->whereDate('date', '2026-09-24')->exists())->toBeTrue();
    expect($aDemarrer->fresh()->statut)->toBe(StatutSeance::EnCours);
    expect($oubliee->fresh()->statut)->toBe(StatutSeance::Cloturee);
});

it('does not start a séance of today before its hour', function () {
    Carbon::setTestNow('2026-09-22 16:59:00');
    $seance = Seance::factory()->create(['date' => '2026-09-22', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    expect(app(SeanceCycleDeVie::class)->demarrer())->toBe(0);
    expect($seance->fresh()->statut)->toBe(StatutSeance::AVenir);
});
```

Add to `tests/Unit/Services/SeanceGeneratorTest.php`:

```php
it('does not create today\'s séance once its hour has passed', function () {
    Carbon::setTestNow('2026-09-22 18:00:00'); // mardi

    $planning = PlanningRepetition::factory()->create(['jour_semaine' => 2, 'heure_debut' => '17:00:00', 'actif' => true]);

    (new SeanceGenerator)->genererPourLesProchainsJours(14);

    expect(Seance::where('planning_repetition_id', $planning->id)->whereDate('date', '2026-09-22')->exists())->toBeFalse();
    expect(Seance::where('planning_repetition_id', $planning->id)->whereDate('date', '2026-09-29')->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('creates today\'s séance when its hour is still to come', function () {
    Carbon::setTestNow('2026-09-22 16:00:00'); // mardi

    $planning = PlanningRepetition::factory()->create(['jour_semaine' => 2, 'heure_debut' => '17:00:00', 'actif' => true]);

    (new SeanceGenerator)->genererPourLesProchainsJours(14);

    expect(Seance::where('planning_repetition_id', $planning->id)->whereDate('date', '2026-09-22')->where('statut', 'a_venir')->exists())->toBeTrue();

    Carbon::setTestNow();
});
```

- [ ] **Step 2: Run them to see them fail**

Run: `php artisan test tests/Unit/Services/SeanceCycleDeVieTest.php tests/Unit/Services/SeanceGeneratorTest.php`
Expected: FAIL (`SeanceCycleDeVie` does not exist, and today's past-hour séance is created).

- [ ] **Step 3: Write the service and fix the generator**

Create `app/Services/SeanceCycleDeVie.php`:

```php
<?php

namespace App\Services;

use App\Enums\StatutSeance;
use App\Models\Seance;
use Illuminate\Support\Carbon;

/**
 * Seul point de passage des changements d'état des séances : le
 * planificateur, le middleware SynchroniserSeances et les écrans de
 * planning l'appellent, pour que l'application reste à jour même quand
 * `schedule:work` ne tourne pas.
 */
class SeanceCycleDeVie
{
    public function __construct(private SeanceGenerator $generateur) {}

    public function synchroniser(): void
    {
        $this->generer();
        $this->demarrer();
        $this->cloturer();
    }

    public function generer(int $jours = 14): int
    {
        return $this->generateur->genererPourLesProchainsJours($jours);
    }

    public function demarrer(): int
    {
        return Seance::where('statut', StatutSeance::AVenir)
            ->whereDate('date', today())
            ->get()
            ->filter(fn (Seance $seance) => $seance->heurePrevueCarbon()->lessThanOrEqualTo(Carbon::now()))
            ->each(fn (Seance $seance) => $seance->update(['statut' => StatutSeance::EnCours]))
            ->count();
    }

    /**
     * En cours : le coach a oublié de clôturer. À venir : la date est passée
     * sans que la séance démarre. Dans les deux cas Seance::clore() est la
     * bonne transition finale (elle matérialise les absences).
     */
    public function cloturer(): int
    {
        return Seance::whereIn('statut', [StatutSeance::EnCours, StatutSeance::AVenir])
            ->whereDate('date', '<', today())
            ->get()
            ->each(fn (Seance $seance) => $seance->clore())
            ->count();
    }
}
```

In `app/Services/SeanceGenerator.php`, inside the `for` loop, right after the `isoWeekday()` check and its `continue;`, add:

```php
                // Un créneau créé aujourd'hui après son heure ne doit pas produire de
                // séance du jour : elle serait clôturée demain avec toutes les filles absentes.
                if ($date->isToday() && Carbon::parse($date->toDateString().' '.$planning->heure_debut)->isPast()) {
                    continue;
                }
```

- [ ] **Step 4: Delegate the commands to the service**

`app/Console/Commands/DemarrerSeancesCommand.php`:

```php
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
```

`app/Console/Commands/CloturerSeancesCommand.php`:

```php
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
```

`GenererSeancesCommand` keeps calling `SeanceGenerator` directly (unchanged).

- [ ] **Step 5: Run the service and command tests**

Run: `php artisan test tests/Unit/Services/SeanceCycleDeVieTest.php tests/Unit/Services/SeanceGeneratorTest.php tests/Unit/Console`
Expected: PASS.

- [ ] **Step 6: Write the middleware tests**

Create `tests/Feature/SynchroniserSeancesTest.php`:

```php
<?php

use App\Enums\StatutSeance;
use App\Models\Seance;
use App\Services\SeanceCycleDeVie;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Exceptions;

afterEach(fn () => Carbon::setTestNow());

it('synchronises séances on a page request when enabled', function () {
    config(['seances.synchronisation_auto' => true]);
    Carbon::setTestNow('2026-09-22 17:05:00');
    $seance = Seance::factory()->create(['date' => '2026-09-22', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    $this->get(route('kiosque.home'))->assertOk();

    expect($seance->fresh()->statut)->toBe(StatutSeance::EnCours);
});

it('synchronises at most once a minute', function () {
    config(['seances.synchronisation_auto' => true]);
    Carbon::setTestNow('2026-09-22 17:05:00');
    $this->get(route('kiosque.home'));

    $seance = Seance::factory()->create(['date' => '2026-09-22', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);
    $this->get(route('kiosque.home'));
    expect($seance->fresh()->statut)->toBe(StatutSeance::AVenir);

    Carbon::setTestNow('2026-09-22 17:06:01');
    $this->get(route('kiosque.home'));
    expect($seance->fresh()->statut)->toBe(StatutSeance::EnCours);
});

it('does nothing when the automatic synchronisation is disabled', function () {
    config(['seances.synchronisation_auto' => false]);
    Carbon::setTestNow('2026-09-22 17:05:00');
    $seance = Seance::factory()->create(['date' => '2026-09-22', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    $this->get(route('kiosque.home'));

    expect($seance->fresh()->statut)->toBe(StatutSeance::AVenir);
});

it('reports a synchronisation failure without breaking the page', function () {
    config(['seances.synchronisation_auto' => true]);
    Exceptions::fake();
    $this->mock(SeanceCycleDeVie::class)->shouldReceive('synchroniser')->andThrow(new RuntimeException('base indisponible'));

    $this->get(route('kiosque.home'))->assertOk();

    Exceptions::assertReported(RuntimeException::class);
});
```

- [ ] **Step 7: Run them to see them fail**

Run: `php artisan test tests/Feature/SynchroniserSeancesTest.php`
Expected: FAIL (the séance stays `a_venir`).

- [ ] **Step 8: Write the config, the middleware and register it**

Create `config/seances.php`:

```php
<?php

return [
    /*
    | Synchronise le cycle de vie des séances (génération, démarrage, clôture)
    | au passage des requêtes web, au plus une fois par minute. Désactivé dans
    | les tests (phpunit.xml) pour ne pas modifier les scénarios existants.
    */
    'synchronisation_auto' => env('SEANCES_SYNCHRONISATION_AUTO', true),
];
```

Create `app/Http/Middleware/SynchroniserSeances.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Services\SeanceCycleDeVie;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SynchroniserSeances
{
    public function __construct(private SeanceCycleDeVie $cycleDeVie) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (config('seances.synchronisation_auto') && Cache::add('seances:synchronisation', true, 60)) {
            try {
                $this->cycleDeVie->synchroniser();
            } catch (Throwable $e) {
                // Une synchronisation ratée ne doit pas bloquer la page : on la
                // journalise, la suivante réessaiera dans une minute.
                report($e);
            }
        }

        return $next($request);
    }
}
```

In `bootstrap/app.php`, add `use App\Http\Middleware\SynchroniserSeances;` and, inside `withMiddleware`, after the `alias([...])` call:

```php
        $middleware->web(append: [
            SynchroniserSeances::class,
        ]);
```

In `phpunit.xml`, inside `<php>`, add:

```xml
        <env name="APP_LOCALE" value="fr"/>
        <env name="SEANCES_SYNCHRONISATION_AUTO" value="false"/>
```

In `.env.example`, after the `APP_FALLBACK_LOCALE` line, add:

```
# Démarre, clôture et génère les séances au passage des pages (au plus une fois
# par minute), en plus du planificateur. Laisser à true.
SEANCES_SYNCHRONISATION_AUTO=true
```

- [ ] **Step 9: Run the middleware tests**

Run: `php artisan test tests/Feature/SynchroniserSeancesTest.php`
Expected: PASS.

- [ ] **Step 10: Generate séances as soon as a créneau is saved, and show the coach's next séance**

Add to `tests/Feature/Admin/PlanningManagementTest.php`:

```php
it('generates the new créneau\'s upcoming séances immediately', function () {
    Carbon::setTestNow('2026-09-26 10:37:00'); // samedi, comme la correction #9
    $coach = Coach::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.plannings.store'), [
        'jour_semaine' => 1,
        'heure_debut' => '17:00',
        'coach_id' => $coach->id,
    ]);

    expect(Seance::where('coach_id', $coach->id)->whereDate('date', '2026-09-28')->where('statut', 'a_venir')->exists())->toBeTrue();

    Carbon::setTestNow();
});
```

Add to `tests/Feature/Coach/PointageGroupeTest.php` (add `use Illuminate\Support\Carbon;` at the top if missing):

```php
it('shows the coach\'s next séance even when it is on a later day', function () {
    Carbon::setTestNow('2026-09-26 12:00:00');
    $coach = Coach::factory()->create();
    $lundi = Seance::factory()->create([
        'coach_id' => $coach->id,
        'statut' => StatutSeance::AVenir,
        'date' => '2026-09-28',
        'heure_prevue' => '17:00:00',
    ]);

    $this->actingAs($coach->user)
        ->get(route('coach.seance'))
        ->assertOk()
        ->assertViewHas('seance', fn ($seance) => $seance?->is($lundi));

    Carbon::setTestNow();
});
```

Run: `php artisan test tests/Feature/Admin/PlanningManagementTest.php tests/Feature/Coach/PointageGroupeTest.php`
Expected: the créneau test FAILS (no séance until the nightly job); the coach test may already pass.

In `app/Http/Controllers/Admin/PlanningController.php`, replace `use App\Services\SeanceGenerator;` with `use App\Services\SeanceCycleDeVie;`, then:

```php
    public function store(StorePlanningRequest $request, SeanceCycleDeVie $cycleDeVie): RedirectResponse
    {
        PlanningRepetition::create($request->validated());

        $cycleDeVie->synchroniser();

        return redirect()->route('admin.plannings.index')->with('message', 'Créneau ajouté.');
    }
```

and replace the end of `reconcilerSeancesFutures()` (the `if ($planning->actif) { ... }` block) with:

```php
        // Le générateur ignore les créneaux inactifs : on peut toujours resynchroniser.
        app(SeanceCycleDeVie::class)->synchroniser();
```

In `app/Http/Controllers/Coach/PointageController.php`, in `show()`, add `->whereDate('date', '>=', today())` to the fallback query so it reads:

```php
            ?? Seance::where('coach_id', $coach?->id)
                ->where('statut', 'a_venir')
                ->whereDate('date', '>=', today())
                ->orderBy('date')
                ->orderBy('heure_prevue')
                ->first();
```

- [ ] **Step 11: Full suite, Pint, commit**

Run: `php artisan test` (expected: all green) then `vendor/bin/pint --dirty`.

```bash
git checkout -- package-lock.json 2>/dev/null; git add -A
git commit -m "$(cat <<'EOF'
feat(seances): keep séances live without the scheduler

A SeanceCycleDeVie service now owns generation, start and clôture; the
commands delegate to it and a web middleware runs it at most once a
minute. Saving a créneau generates its séances right away, which was
why a Monday répétition never showed up for its coach.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Bandeau de séance, notifications et kiosque en temps réel (#12, #16)

**Files:**
- Create: `app/Services/EtatSeances.php`
- Create: `app/Http/Controllers/EtatSeancesController.php`
- Create: `app/Http/Controllers/Kiosque/EtatController.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Kiosque/IdentificationController.php` (`home()`)
- Create: `resources/views/components/bandeau-seance.blade.php`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/kiosque/accueil.blade.php`
- Create: `resources/js/bandeau-seance.js`, `resources/js/kiosque-etat.js`
- Modify: `resources/js/app.js`, `resources/css/_cafab-app.scss`
- Test: create `tests/Feature/EtatSeancesTest.php`, create `tests/Feature/Kiosque/EtatTest.php`, modify `tests/Feature/Kiosque/IdentificationTest.php`

**Interfaces:**
- Consumes: the web middleware from Task 2 (not needed by the tests, which freeze time).
- Produces:
  - `App\Services\EtatSeances::pourUtilisateur(User $user): array` returning `['lignes' => list<array{id: int, type: 'en_cours'|'prochaine', texte: string}>]`.
  - `App\Services\EtatSeances::pourKiosque(): array` returning `['en_cours_id' => ?int, 'prochaine' => ?array{id: int, texte: string}]`.
  - `EtatSeances::HORIZON_JOURS = 14`.
  - Routes `etat-seances` (`GET /etat-seances`, auth + `role:admin,coach`) and `kiosque.etat` (`GET /kiosque/etat`, public).
  - Blade component `<x-bandeau-seance />`, rendered at the top of `<main>` in `layouts/app.blade.php`.
  - The kiosk home view receives `$prochaine` (`?array{id: int, texte: string}`).

Texts are built on the server so the Blade first render and the JS refresh can never disagree. Formats:
- en cours: `Répétition en cours depuis 17:00 · 8 présentes sur 12` (singular `présente` for 0 or 1), followed by ` · <nom du coach>` for the admin only;
- prochaine: `Prochaine répétition : lundi 28 septembre à 17:00` (`aujourd'hui à 17:00`, `demain à 17:00` when relevant), followed by ` · <nom du coach>` for the admin only;
- the `prochaine` line appears only when no séance is in progress for that user.

- [ ] **Step 1: Write the endpoint tests**

Create `tests/Feature/EtatSeancesTest.php`:

```php
<?php

use App\Enums\StatutPonctualite;
use App\Enums\StatutSeance;
use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-26 17:10:00'); // samedi
    $this->coach = Coach::factory()->create();
    $this->coach->user->update(['name' => 'Prudence Aïvodji']);
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

afterEach(fn () => Carbon::setTestNow());

it('answers 401 JSON to a guest', function () {
    $this->getJson(route('etat-seances'))->assertUnauthorized();
});

it('shows the admin every séance in progress with its attendance and its coach', function () {
    $seance = Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-09-26', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::EnCours]);
    [$presente] = Fille::factory()->count(2)->create(['statut' => 'actif']);
    Pointage::factory()->create(['seance_id' => $seance->id, 'pointable_id' => $presente->id, 'statut_ponctualite' => StatutPonctualite::ALHeure]);

    $this->actingAs($this->admin)->getJson(route('etat-seances'))
        ->assertOk()
        ->assertExactJson(['lignes' => [[
            'id' => $seance->id,
            'type' => 'en_cours',
            'texte' => 'Répétition en cours depuis 17:00 · 1 présente sur 2 · Prudence Aïvodji',
        ]]]);
});

it('shows a coach only his own séances, without his own name', function () {
    $autreCoach = Coach::factory()->create();
    Seance::factory()->create(['coach_id' => $autreCoach->id, 'date' => '2026-09-26', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::EnCours]);
    $lundi = Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-09-28', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    $this->actingAs($this->coach->user)->getJson(route('etat-seances'))
        ->assertOk()
        ->assertExactJson(['lignes' => [[
            'id' => $lundi->id,
            'type' => 'prochaine',
            'texte' => 'Prochaine répétition : lundi 28 septembre à 17:00',
        ]]]);
});

it('says demain for tomorrow\'s séance', function () {
    Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-09-27', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    $this->actingAs($this->admin)->getJson(route('etat-seances'))
        ->assertOk()
        ->assertJsonPath('lignes.0.texte', 'Prochaine répétition : demain à 17:00 · Prudence Aïvodji');
});

it('ignores séances beyond the 14-day horizon', function () {
    Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-10-15', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    $this->actingAs($this->admin)->getJson(route('etat-seances'))->assertExactJson(['lignes' => []]);
});

it('returns an empty state for a coach account without a coach profile', function () {
    Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-09-26', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::EnCours]);
    $sansProfil = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($sansProfil)->getJson(route('etat-seances'))->assertOk()->assertExactJson(['lignes' => []]);
});

it('renders the banner server-side on the dashboard', function () {
    Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-09-26', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::EnCours]);

    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Répétition en cours depuis 17:00')
        ->assertSee('data-bandeau-seance', false);
});
```

Create `tests/Feature/Kiosque/EtatTest.php`:

```php
<?php

use App\Enums\StatutSeance;
use App\Models\Coach;
use App\Models\Seance;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-26 12:00:00'); // samedi
    $this->coach = Coach::factory()->create();
    $this->coach->user->update(['name' => 'Prudence Aïvodji']);
});

afterEach(fn () => Carbon::setTestNow());

it('exposes the next répétition without any name', function () {
    $lundi = Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-09-28', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    $this->getJson(route('kiosque.etat'))
        ->assertOk()
        ->assertExactJson([
            'en_cours_id' => null,
            'prochaine' => ['id' => $lundi->id, 'texte' => 'Prochaine répétition : lundi 28 septembre à 17:00'],
        ])
        ->assertDontSee('Prudence');
});

it('exposes the séance in progress', function () {
    $enCours = Seance::factory()->create(['coach_id' => $this->coach->id, 'date' => '2026-09-26', 'heure_prevue' => '11:30:00', 'statut' => StatutSeance::EnCours]);

    $this->getJson(route('kiosque.etat'))->assertOk()->assertJsonPath('en_cours_id', $enCours->id);
});
```

Add to `tests/Feature/Kiosque/IdentificationTest.php` (add the `use` lines for `StatutSeance`, `Seance` and `Carbon` if missing):

```php
it('shows the next répétition when none is in progress', function () {
    Carbon::setTestNow('2026-09-26 12:00:00');
    Seance::factory()->create(['date' => '2026-09-28', 'heure_prevue' => '17:00:00', 'statut' => StatutSeance::AVenir]);

    $this->get(route('kiosque.home'))
        ->assertOk()
        ->assertSee('Prochaine répétition : lundi 28 septembre à 17:00')
        ->assertSee('data-kiosque-etat', false);

    Carbon::setTestNow();
});
```

- [ ] **Step 2: Run them to see them fail**

Run: `php artisan test tests/Feature/EtatSeancesTest.php tests/Feature/Kiosque/EtatTest.php tests/Feature/Kiosque/IdentificationTest.php`
Expected: FAIL (routes `etat-seances` and `kiosque.etat` are not defined).

- [ ] **Step 3: Write the service**

Create `app/Services/EtatSeances.php`:

```php
<?php

namespace App\Services;

use App\Enums\StatutPersonne;
use App\Enums\StatutPonctualite;
use App\Enums\StatutSeance;
use App\Enums\UserRole;
use App\Models\Fille;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * État « en direct » des séances, lu par le bandeau (admin, coach) et par
 * le kiosque. Les phrases sont construites ici pour que le premier rendu
 * Blade et les rafraîchissements JavaScript affichent exactement la même chose.
 */
class EtatSeances
{
    public const HORIZON_JOURS = 14;

    /**
     * @return array{lignes: list<array{id: int, type: string, texte: string}>}
     */
    public function pourUtilisateur(User $user): array
    {
        $estAdmin = $user->role === UserRole::Admin;
        // Un compte coach sans fiche Coach ne voit rien (id 0 ne correspond à aucune séance).
        $coachId = $estAdmin ? null : ($user->coach?->id ?? 0);

        $lignes = $this->enCours($coachId)
            ->map(fn (Seance $seance) => [
                'id' => $seance->id,
                'type' => 'en_cours',
                'texte' => $this->texteEnCours($seance, $estAdmin),
            ])
            ->values()
            ->all();

        if ($lignes === [] && ($prochaine = $this->prochaine($coachId)) !== null) {
            $lignes[] = [
                'id' => $prochaine->id,
                'type' => 'prochaine',
                'texte' => 'Prochaine répétition : '.$this->quand($prochaine).($estAdmin ? ' · '.$this->nomCoach($prochaine) : ''),
            ];
        }

        return ['lignes' => $lignes];
    }

    /**
     * Données publiques du kiosque : aucun nom, uniquement des identifiants et des horaires.
     *
     * @return array{en_cours_id: ?int, prochaine: ?array{id: int, texte: string}}
     */
    public function pourKiosque(): array
    {
        $enCours = Seance::where('statut', StatutSeance::EnCours)
            ->whereDate('date', today())
            ->latest('heure_prevue')
            ->first();
        $prochaine = $this->prochaine(null);

        return [
            'en_cours_id' => $enCours?->id,
            'prochaine' => $prochaine ? ['id' => $prochaine->id, 'texte' => 'Prochaine répétition : '.$this->quand($prochaine)] : null,
        ];
    }

    private function enCours(?int $coachId): Collection
    {
        return Seance::with('coach.user')
            ->where('statut', StatutSeance::EnCours)
            ->whereDate('date', today())
            ->when($coachId !== null, fn ($query) => $query->where('coach_id', $coachId))
            ->orderBy('heure_prevue')
            ->get();
    }

    private function prochaine(?int $coachId): ?Seance
    {
        return Seance::with('coach.user')
            ->where('statut', StatutSeance::AVenir)
            ->whereDate('date', '>=', today())
            ->whereDate('date', '<', today()->addDays(self::HORIZON_JOURS))
            ->when($coachId !== null, fn ($query) => $query->where('coach_id', $coachId))
            ->orderBy('date')
            ->orderBy('heure_prevue')
            ->first();
    }

    private function texteEnCours(Seance $seance, bool $avecCoach): string
    {
        $presentes = $seance->pointages()
            ->where('pointable_type', Fille::class)
            ->where('statut_ponctualite', '!=', StatutPonctualite::Absent)
            ->count();
        $total = Fille::where('statut', StatutPersonne::Actif)->count();

        $texte = sprintf(
            'Répétition en cours depuis %s · %d %s sur %d',
            $this->heure($seance),
            $presentes,
            $presentes > 1 ? 'présentes' : 'présente',
            $total,
        );

        return $avecCoach ? $texte.' · '.$this->nomCoach($seance) : $texte;
    }

    private function quand(Seance $seance): string
    {
        $jour = match (true) {
            $seance->date->isToday() => "aujourd'hui",
            $seance->date->isTomorrow() => 'demain',
            default => $seance->date->translatedFormat('l j F'),
        };

        return $jour.' à '.$this->heure($seance);
    }

    private function heure(Seance $seance): string
    {
        return Carbon::parse($seance->heure_prevue)->format('H:i');
    }

    private function nomCoach(Seance $seance): string
    {
        return $seance->coach?->user?->name ?? 'coach inconnu';
    }
}
```

- [ ] **Step 4: Controllers and routes**

Create `app/Http/Controllers/EtatSeancesController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\EtatSeances;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EtatSeancesController extends Controller
{
    public function __invoke(Request $request, EtatSeances $etat): JsonResponse
    {
        return response()->json($etat->pourUtilisateur($request->user()));
    }
}
```

Create `app/Http/Controllers/Kiosque/EtatController.php`:

```php
<?php

namespace App\Http\Controllers\Kiosque;

use App\Http\Controllers\Controller;
use App\Services\EtatSeances;
use Illuminate\Http\JsonResponse;

class EtatController extends Controller
{
    public function __invoke(EtatSeances $etat): JsonResponse
    {
        return response()->json($etat->pourKiosque());
    }
}
```

In `routes/web.php`, add the imports:

```php
use App\Http\Controllers\EtatSeancesController;
use App\Http\Controllers\Kiosque\EtatController as KiosqueEtatController;
```

inside the existing `Route::middleware(['auth', 'role:admin,coach'])->group(...)` block, add:

```php
    Route::get('etat-seances', EtatSeancesController::class)->name('etat-seances');
```

and inside the `Route::prefix('kiosque')->name('kiosque.')->group(...)` block, add:

```php
    Route::get('etat', KiosqueEtatController::class)->name('etat');
```

In `app/Http/Controllers/Kiosque/IdentificationController.php`, add `use App\Services\EtatSeances;` and replace `home()`:

```php
    public function home(EtatSeances $etat): View
    {
        $seance = Seance::where('statut', 'en_cours')->whereDate('date', today())->latest('heure_prevue')->first();

        return view('kiosque.accueil', [
            'seance' => $seance,
            'prochaine' => $seance ? null : $etat->pourKiosque()['prochaine'],
        ]);
    }
```

- [ ] **Step 5: The banner component and the layout**

Create `resources/views/components/bandeau-seance.blade.php`:

```blade
@php($etat = app(\App\Services\EtatSeances::class)->pourUtilisateur(auth()->user()))
<div class="bandeau-seance" data-bandeau-seance data-url="{{ route('etat-seances') }}" @if ($etat['lignes'] === []) hidden @endif>
    <div class="bandeau-seance-lignes" data-bandeau-lignes>
        @foreach ($etat['lignes'] as $ligne)
            <p class="bandeau-ligne {{ $ligne['type'] === 'en_cours' ? 'bandeau-en-cours' : 'bandeau-prochaine' }}">{{ $ligne['texte'] }}</p>
        @endforeach
    </div>
    <button type="button" class="btn-outline btn-sm" data-bandeau-notifications hidden>Activer les notifications</button>
</div>
```

In `resources/views/layouts/app.blade.php`, make `<x-bandeau-seance />` the first child of `<main class="main">` (before the flash messages).

- [ ] **Step 6: Kiosk home: next répétition, live state, centred code pad**

In `resources/views/kiosque/accueil.blade.php`:

1. Replace the opening `<div class="kiosk d-flex flex-column">` with:

```blade
    <div class="kiosk d-flex flex-column"
         data-kiosque-etat
         data-url="{{ route('kiosque.etat') }}"
         data-en-cours-id="{{ $seance?->id }}"
         data-prochaine-id="{{ $prochaine['id'] ?? '' }}">
```

2. Replace the `@else` branch of the subtitle so the block reads:

```blade
            <p class="field-hint kiosk-subtitle mb-4">
                @if ($seance)
                    Répétition en cours, début prévu {{ \Illuminate\Support\Carbon::parse($seance->heure_prevue)->format('H:i') }}
                @else
                    Aucune répétition en cours pour le moment.
                    @if ($prochaine)
                        <br>{{ $prochaine['texte'] }}.
                    @endif
                @endif
            </p>
```

3. On the row of the four code cells (`<div class="d-flex gap-2 mb-4">` that holds the `<template x-for="i in 4">`), add `justify-content-center`.

Append to `resources/css/_cafab-app.scss` (the existing `.kiosk-pin-panel` rule only sets `max-width`; this block centres the whole panel):

```scss
/* Kiosque : bloc « Tape ton code » centré dans la page (#16) */
.kiosk-pin-panel {
  width: 100%;
  margin-inline: auto;
  text-align: center;
}

/* Bandeau de séance (haut des écrans admin et coach) */
.bandeau-seance {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  background: var(--info-bg);
  color: var(--info-ink);
  padding: 12px 34px;
  border-bottom: 1px solid var(--border);
}

// Les classes ci-dessus fixent display : sans cette règle, l'attribut hidden resterait sans effet.
.bandeau-seance[hidden],
.bandeau-seance [hidden] {
  display: none !important;
}

.bandeau-seance-lignes {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.bandeau-ligne {
  margin: 0;
  font-weight: 600;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.bandeau-ligne::before {
  content: "";
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: currentColor;
  flex: none;
}

.bandeau-en-cours {
  color: var(--st-fort-fg);
}
```

- [ ] **Step 7: The two polling modules**

Create `resources/js/bandeau-seance.js`:

```js
const INTERVALLE_MS = 30000;
const CLE_NOTIFIEES = 'cafab.seances-notifiees';

function lireNotifiees() {
    try {
        return JSON.parse(localStorage.getItem(CLE_NOTIFIEES) ?? '[]');
    } catch {
        return [];
    }
}

function memoriserNotifiees(ids) {
    try {
        localStorage.setItem(CLE_NOTIFIEES, JSON.stringify(ids.slice(-50)));
    } catch {
        // Stockage indisponible (navigation privée) : on notifiera peut-être deux fois, sans gravité.
    }
}

function afficher(bandeau, lignes) {
    const conteneur = bandeau.querySelector('[data-bandeau-lignes]');
    conteneur.replaceChildren(...lignes.map((ligne) => {
        const paragraphe = document.createElement('p');
        paragraphe.className = `bandeau-ligne ${ligne.type === 'en_cours' ? 'bandeau-en-cours' : 'bandeau-prochaine'}`;
        paragraphe.textContent = ligne.texte;

        return paragraphe;
    }));
    bandeau.hidden = lignes.length === 0;
}

function notifier(lignes) {
    if (!('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }

    const dejaNotifiees = lireNotifiees();
    const nouvelles = lignes.filter((ligne) => ligne.type === 'en_cours' && !dejaNotifiees.includes(ligne.id));

    nouvelles.forEach((ligne) => {
        // Le tag évite un doublon si deux onglets ouverts notifient au même moment.
        new Notification('Répétition en cours', { body: ligne.texte, tag: `seance-${ligne.id}` });
    });

    if (nouvelles.length > 0) {
        memoriserNotifiees([...dejaNotifiees, ...nouvelles.map((ligne) => ligne.id)]);
    }
}

function mettreAJourBouton(bouton) {
    bouton.hidden = !('Notification' in window) || Notification.permission !== 'default';
}

async function rafraichir(bandeau) {
    try {
        const reponse = await fetch(bandeau.dataset.url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!reponse.ok || reponse.redirected) {
            return; // Session expirée, changement de mot de passe exigé… on garde le dernier état affiché.
        }

        const donnees = await reponse.json();
        afficher(bandeau, donnees.lignes);
        notifier(donnees.lignes);
    } catch {
        // Réseau indisponible ou réponse non JSON : on réessaiera au prochain tour.
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const bandeau = document.querySelector('[data-bandeau-seance]');

    if (!bandeau) {
        return;
    }

    const bouton = bandeau.querySelector('[data-bandeau-notifications]');
    mettreAJourBouton(bouton);
    bouton.addEventListener('click', async () => {
        await Notification.requestPermission();
        mettreAJourBouton(bouton);
        rafraichir(bandeau);
    });

    rafraichir(bandeau);
    setInterval(() => rafraichir(bandeau), INTERVALLE_MS);
});
```

Create `resources/js/kiosque-etat.js`:

```js
const INTERVALLE_MS = 30000;

document.addEventListener('DOMContentLoaded', () => {
    const racine = document.querySelector('[data-kiosque-etat]');

    if (!racine) {
        return;
    }

    const etatInitial = `${racine.dataset.enCoursId}|${racine.dataset.prochaineId}`;

    setInterval(async () => {
        // Ne jamais recharger pendant qu'une fille tape son code.
        const champCode = document.querySelector('input[name="pin"]');
        if (champCode && champCode.value !== '') {
            return;
        }

        try {
            const reponse = await fetch(racine.dataset.url, { headers: { Accept: 'application/json' } });

            if (!reponse.ok) {
                return;
            }

            const etat = await reponse.json();
            const etatActuel = `${etat.en_cours_id ?? ''}|${etat.prochaine?.id ?? ''}`;

            if (etatActuel !== etatInitial) {
                window.location.reload();
            }
        } catch {
            // Réseau indisponible : on réessaie au prochain tour.
        }
    }, INTERVALLE_MS);
});
```

In `resources/js/app.js`, after `import './flash-messages';`, add:

```js
import './bandeau-seance';
import './kiosque-etat';
```

- [ ] **Step 8: Run the tests**

Run: `php artisan test tests/Feature/EtatSeancesTest.php tests/Feature/Kiosque`
Expected: PASS.

- [ ] **Step 9: Build and full suite, Pint, commit**

Run: `npm run build` (expected: build succeeds), `php artisan test` (all green), `vendor/bin/pint --dirty`.

```bash
git checkout -- package-lock.json 2>/dev/null; git add -A
git commit -m "$(cat <<'EOF'
feat(seances): live banner, browser notifications and a self-updating kiosk

Admin and coach screens show the séance in progress or the next one,
refreshed every 30 seconds, with an opt-in browser notification when a
séance starts. The kiosk shows the next répétition and reloads itself
when a séance starts, never while a code is being typed; its code pad
is now centred.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Horloge en temps réel et logo commun des écrans de connexion (#13)

**Files:**
- Create: `resources/views/components/horloge.blade.php`
- Create: `resources/js/horloge.js`
- Modify: `resources/js/app.js`, `resources/css/_cafab-app.scss`
- Modify: `resources/views/layouts/app.blade.php`, `resources/views/layouts/guest.blade.php`
- Modify: `resources/views/auth/login.blade.php`, `forgot-password.blade.php`, `reset-password.blade.php`, `confirm-password.blade.php`, `verify-email.blade.php`
- Modify: `resources/views/kiosque/accueil.blade.php`
- Test: create `tests/Feature/HorlogeTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `<x-horloge variante="sidebar|auth|kiosque" />`: a `<p data-horloge data-serveur-ms data-fuseau>` whose first render already contains the server time, e.g. `samedi 26 septembre 2026 · 12:27:48`.
  - `layouts/guest.blade.php` now renders the CAFAB logo, the clock and the "Présence & paiements" overline for every authentication screen; views using `<x-guest-layout>` (including the Task 6 forced-change screen) must NOT render their own logo or overline.

- [ ] **Step 1: Write the tests**

Create `tests/Feature/HorlogeTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(fn () => Carbon::setTestNow('2026-09-26 12:27:48'));
afterEach(fn () => Carbon::setTestNow());

it('shows the server date and time under the logo on the login screen', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('samedi 26 septembre 2026 · 12:27:48')
        ->assertSee('data-horloge', false);
});

it('shows it under the logo in the sidebar', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('samedi 26 septembre 2026 · 12:27:48');
});

it('shows it in the kiosk top bar', function () {
    $this->get(route('kiosque.home'))->assertOk()->assertSee('samedi 26 septembre 2026 · 12:27:48');
});

it('renders the CAFAB logo once on the login card', function () {
    // Deux occurrences attendues : l'icône de l'onglet et le logo de la carte.
    expect(substr_count($this->get(route('login'))->getContent(), 'images/logo-cafab.png'))->toBe(2);
});
```

- [ ] **Step 2: Run them to see them fail**

Run: `php artisan test tests/Feature/HorlogeTest.php`
Expected: FAIL (no clock text, and no `data-horloge` attribute).

- [ ] **Step 3: The component and the script**

Create `resources/views/components/horloge.blade.php`:

```blade
@props(['variante' => 'sidebar'])
<p {{ $attributes->merge(['class' => 'horloge horloge-'.$variante]) }}
   data-horloge
   data-serveur-ms="{{ now()->getTimestampMs() }}"
   data-fuseau="{{ config('app.timezone') }}">{{ now()->translatedFormat('l j F Y') }} · {{ now()->format('H:i:s') }}</p>
```

Create `resources/js/horloge.js`:

```js
// Affiche l'heure du serveur (et non celle du PC) : l'écart est mesuré au
// chargement de la page, puis l'horloge avance chaque seconde.
function formater(date, fuseau) {
    const jour = new Intl.DateTimeFormat('fr-FR', {
        timeZone: fuseau,
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);
    const heure = new Intl.DateTimeFormat('fr-FR', {
        timeZone: fuseau,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
    }).format(date);

    return `${jour} · ${heure}`;
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-horloge]').forEach((horloge) => {
        const ecart = Number(horloge.dataset.serveurMs) - Date.now();
        const fuseau = horloge.dataset.fuseau;
        const tic = () => {
            horloge.textContent = formater(new Date(Date.now() + ecart), fuseau);
        };

        tic();
        setInterval(tic, 1000);
    });
});
```

In `resources/js/app.js`, add `import './horloge';` after the imports added by Task 3.

Append to `resources/css/_cafab-app.scss`:

```scss
/* Horloge en temps réel (#13) */
.horloge {
  margin: 0;
  font-family: var(--font-mono);
  font-size: 12px;
  font-variant-numeric: tabular-nums;
}

.horloge-sidebar {
  color: var(--sidebar-text);
  text-align: center;
  margin: -16px 0 24px;
}

.horloge-auth {
  color: var(--ink-3);
  margin-bottom: 12px;
}

.horloge-kiosque {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: 20px;
  color: var(--ink);
}
```

- [ ] **Step 4: Place the clock**

In `resources/views/layouts/app.blade.php`, right after the closing `</div>` of `<div class="logo-box">`, add `<x-horloge variante="sidebar" />`.

Replace the body of `resources/views/layouts/guest.blade.php` so the card reads:

```blade
    <div class="auth-shell">
        <div class="auth-card">
            <div class="logo-box">
                <img src="{{ asset('images/logo-cafab.png') }}" alt="CAFAB">
            </div>
            <x-horloge variante="auth" />
            <p class="overline mb-1">Présence &amp; paiements</p>

            {{ $slot }}
        </div>
    </div>
```

In each of `resources/views/auth/login.blade.php`, `forgot-password.blade.php`, `reset-password.blade.php`, `confirm-password.blade.php` and `verify-email.blade.php`, delete the `<div class="logo-box">…</div>` block (three lines) and the `<p class="overline mb-1">Présence &amp; paiements</p>` line at the top: the layout renders them now.

In `resources/views/kiosque/accueil.blade.php`, replace the whole `<span class="page-title kiosk-clock" x-data=... x-text="heure"></span>` element with `<x-horloge variante="kiosque" />`.

- [ ] **Step 5: Run the tests, build, full suite, commit**

Run: `php artisan test tests/Feature/HorlogeTest.php` (expected: PASS), `npm run build`, `php artisan test` (all green), `vendor/bin/pint --dirty`.

```bash
git checkout -- package-lock.json 2>/dev/null; git add -A
git commit -m "$(cat <<'EOF'
feat(ui): real-time server clock under the CAFAB logo

Day, date and time to the second, following the server's Porto-Novo
time rather than the PC's, under the logo on the login screens and in
the sidebar, and in the kiosk top bar. The auth screens now share the
logo block from the guest layout instead of repeating it.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Connexion, traductions et mot de passe oublié par email (#1, #2, #3)

**Files:**
- Create: `lang/fr/auth.php`, `lang/fr/passwords.php`, `lang/fr/pagination.php`, `lang/fr/validation.php`, `lang/fr.json`
- Modify: `phpunit.xml`, `.env.example`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Http/Requests/Auth/LoginRequest.php`
- Modify: `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- Create: `resources/views/components/champ-mot-de-passe.blade.php`
- Modify: `resources/js/password-toggle.js`
- Modify: `resources/views/auth/login.blade.php`, `reset-password.blade.php`, `confirm-password.blade.php`, `forgot-password.blade.php`
- Test: create `tests/Feature/Auth/SeSouvenirDeMoiTest.php`, create `tests/Feature/TraductionsTest.php`, modify `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/Auth/PasswordResetTest.php`, `tests/Feature/Auth/PasswordUpdateTest.php`

**Interfaces:**
- Consumes: the guest layout from Task 4.
- Produces:
  - `Password::defaults()` = `Password::min(8)->letters()->mixedCase()->numbers()`.
  - `<x-champ-mot-de-passe id="..." name="..." autocomplete="..." :required="true|false" />`: an input `type="password"` plus an eye button `data-toggle-password="<id>"`. Any extra attribute (placeholder, value...) goes on the `<input>`. Every password field added by later tasks uses it.
  - `resources/js/password-toggle.js` toggles the field whose `id` is given by `data-toggle-password`.
  - French translations with English fallback (`APP_FALLBACK_LOCALE=en`): a rule without a French message shows the framework's English text, never a raw key.
  - Forgot password always answers `__('passwords.sent')`.

The existing tests set new passwords to `password` and `new-password`, which the new rule rejects. This task switches them to `Nouveau-Pass1`.

- [ ] **Step 1: Write the tests**

Create `tests/Feature/Auth/SeSouvenirDeMoiTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('sets the remember-me cookie when the box is checked', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => 'on'])
        ->assertCookie(Auth::guard()->getRecallerName());

    expect($user->fresh()->remember_token)->not->toBeNull();
});

it('does not set it when the box is unchecked', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertCookieMissing(Auth::guard()->getRecallerName());
});

it('logs the user back in from the cookie once the session is gone', function () {
    $user = User::factory()->create();
    $recaller = Auth::guard()->getRecallerName();

    $valeur = $this->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => 'on'])
        ->getCookie($recaller)
        ->getValue();

    $this->flushSession();
    $this->app['auth']->forgetGuards();

    $this->withCookie($recaller, $valeur)->get(route('dashboard'))->assertOk();
    $this->assertAuthenticatedAs($user);
});

it('forgets the cookie on logout', function () {
    // Laravel n'expire le cookie que si la requête de déconnexion le porte : on se connecte donc vraiment.
    $user = User::factory()->create();
    $recaller = Auth::guard()->getRecallerName();

    $valeur = $this->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => 'on'])
        ->getCookie($recaller)
        ->getValue();

    $this->withCookie($recaller, $valeur)->post(route('logout'))->assertCookieExpired($recaller);
});
```

Create `tests/Feature/TraductionsTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Validation\Rules\Password;

it('shows validation errors in French', function () {
    $this->from(route('login'))
        ->post(route('login'), ['email' => '', 'password' => ''])
        ->assertSessionHasErrors(['email' => 'Le champ email est obligatoire.']);
});

it('explains the password rule in French', function () {
    $erreur = validator(['password' => 'abc'], ['password' => Password::defaults()])->errors()->first('password');

    expect($erreur)->toContain('au moins 8 caractères');
});

it('writes the password reset email in French', function () {
    $mail = (new ResetPassword('jeton'))->toMail(User::factory()->create());

    expect($mail->subject)->toBe('Réinitialisation de votre mot de passe');
    expect($mail->actionText)->toBe('Réinitialiser le mot de passe');
});
```

Add to `tests/Feature/Auth/AuthenticationTest.php` (it is a PHPUnit class: add a method to the class):

```php
    public function test_users_can_authenticate_with_an_email_typed_in_capitals_with_spaces(): void
    {
        $user = User::factory()->create(['email' => 'coach@cafab.bj']);

        $this->post('/login', ['email' => '  Coach@CAFAB.bj ', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_login_screen_has_a_working_password_toggle(): void
    {
        $this->get('/login')->assertSee('data-toggle-password="password"', false);
    }
```

In `tests/Feature/Auth/PasswordResetTest.php`, in `test_password_can_be_reset_with_valid_token`, replace both `'password'` values of the posted `password` / `password_confirmation` with `'Nouveau-Pass1'`, and add these methods to the class:

```php
    public function test_the_same_message_is_shown_for_an_unknown_address(): void
    {
        Notification::fake();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'inconnu@cafab.bj'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));

        Notification::assertNothingSent();
    }

    public function test_the_known_address_gets_the_same_message(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', __('passwords.sent'));
    }

    public function test_a_reset_link_is_sent_for_an_address_typed_in_capitals(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'coach@cafab.bj']);

        $this->post('/forgot-password', ['email' => '  Coach@CAFAB.bj ']);

        Notification::assertSentTo($user, ResetPassword::class);
    }
```

In `tests/Feature/Auth/PasswordUpdateTest.php`, replace every `'new-password'` with `'Nouveau-Pass1'`.

- [ ] **Step 2: Run them to see them fail**

Run: `php artisan test tests/Feature/Auth tests/Feature/TraductionsTest.php`
Expected: FAIL (English messages, unknown-address error, capitals rejected, no `data-toggle-password`).

- [ ] **Step 3: French translation files**

Create `lang/fr/auth.php`:

```php
<?php

return [
    'failed' => 'Ces identifiants ne correspondent à aucun compte.',
    'password' => 'Le mot de passe est incorrect.',
    'throttle' => 'Trop de tentatives de connexion. Réessayez dans :seconds secondes.',
];
```

Create `lang/fr/passwords.php`:

```php
<?php

return [
    'reset' => 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.',
    'sent' => 'Si un compte correspond à cette adresse, un lien de réinitialisation vient d\'être envoyé.',
    'throttled' => 'Veuillez patienter avant de réessayer.',
    'token' => 'Ce lien de réinitialisation n\'est plus valide. Demandez-en un nouveau.',
    'user' => 'Ce lien ne correspond pas à cette adresse email.',
];
```

Create `lang/fr/pagination.php`:

```php
<?php

return [
    'previous' => '&laquo; Précédent',
    'next' => 'Suivant &raquo;',
];
```

Create `lang/fr/validation.php` (only the rules this app uses; anything else falls back to English):

```php
<?php

return [
    'after_or_equal' => 'Le champ :attribute doit être une date postérieure ou égale au :date.',
    'array' => 'Le champ :attribute doit être une liste.',
    'between' => [
        'array' => 'Le champ :attribute doit contenir entre :min et :max éléments.',
        'file' => 'Le fichier :attribute doit peser entre :min et :max kilo-octets.',
        'numeric' => 'Le champ :attribute doit être compris entre :min et :max.',
        'string' => 'Le champ :attribute doit contenir entre :min et :max caractères.',
    ],
    'boolean' => 'Le champ :attribute doit valoir vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'date' => 'Le champ :attribute doit être une date valide.',
    'date_format' => 'Le champ :attribute doit respecter le format :format.',
    'decimal' => 'Le champ :attribute doit comporter :decimal décimales.',
    'email' => 'Le champ :attribute doit être une adresse email valide.',
    'enum' => 'La valeur choisie pour :attribute est invalide.',
    'exists' => 'La valeur choisie pour :attribute est invalide.',
    'file' => 'Le champ :attribute doit être un fichier.',
    'gt' => [
        'array' => 'Le champ :attribute doit contenir plus de :value éléments.',
        'file' => 'Le fichier :attribute doit peser plus de :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être supérieur à :value.',
        'string' => 'Le champ :attribute doit contenir plus de :value caractères.',
    ],
    'in' => 'La valeur choisie pour :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'lowercase' => 'Le champ :attribute doit être en minuscules.',
    'max' => [
        'array' => 'Le champ :attribute ne doit pas contenir plus de :max éléments.',
        'file' => 'Le fichier :attribute ne doit pas dépasser :max kilo-octets.',
        'numeric' => 'Le champ :attribute ne doit pas dépasser :max.',
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'mimes' => 'Le fichier :attribute doit être de type : :values.',
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        'file' => 'Le fichier :attribute doit peser au moins :min kilo-octets.',
        'numeric' => 'Le champ :attribute doit valoir au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'password' => [
        'letters' => 'Le champ :attribute doit contenir au moins une lettre.',
        'mixed' => 'Le champ :attribute doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols' => 'Le champ :attribute doit contenir au moins un caractère spécial.',
        'uncompromised' => 'Ce :attribute est apparu dans une fuite de données. Choisissez-en un autre.',
    ],
    'required' => 'Le champ :attribute est obligatoire.',
    'required_if' => 'Le champ :attribute est obligatoire quand :other vaut :value.',
    'size' => [
        'array' => 'Le champ :attribute doit contenir :size éléments.',
        'file' => 'Le fichier :attribute doit peser :size kilo-octets.',
        'numeric' => 'Le champ :attribute doit valoir :size.',
        'string' => 'Le champ :attribute doit contenir :size caractères.',
    ],
    'string' => 'Le champ :attribute doit être un texte.',
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',

    'attributes' => [
        'coach_id' => 'coach',
        'contact' => 'contact',
        'current_password' => 'mot de passe actuel',
        'date' => 'date',
        'date_debut' => 'date de début',
        'date_entree' => 'date d\'entrée',
        'date_fin' => 'date de fin',
        'email' => 'email',
        'fichier' => 'fichier',
        'heure' => 'heure',
        'heure_debut' => 'heure de début',
        'heure_prevue' => 'heure prévue',
        'jour_semaine' => 'jour',
        'lieu' => 'lieu',
        'minutes_retard' => 'minutes de retard',
        'montant' => 'montant',
        'montant_defaut' => 'montant par défaut',
        'motif' => 'motif',
        'name' => 'nom',
        'nom' => 'nom',
        'password' => 'mot de passe',
        'pin' => 'code',
        'prenom' => 'prénom',
        'statut' => 'statut',
        'statut_ponctualite' => 'statut',
        'titre' => 'titre',
    ],
];
```

Create `lang/fr.json`. Open `vendor/laravel/framework/src/Illuminate/Notifications/resources/views/email.blade.php` and `vendor/laravel/framework/src/Illuminate/Auth/Notifications/ResetPassword.php` first: the keys below must match the strings those files pass to `__()` / `@lang()` character for character (adjust a key if the installed version differs).

```json
{
    "Reset Password Notification": "Réinitialisation de votre mot de passe",
    "You are receiving this email because we received a password reset request for your account.": "Vous recevez cet email car une réinitialisation du mot de passe de votre compte a été demandée.",
    "Reset Password": "Réinitialiser le mot de passe",
    "This password reset link will expire in :count minutes.": "Ce lien expire dans :count minutes.",
    "If you did not request a password reset, no further action is required.": "Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.",
    "Hello!": "Bonjour,",
    "Whoops!": "Oups !",
    "Regards,": "Cordialement,",
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\ninto your web browser:": "Si le bouton « :actionText » ne fonctionne pas, copiez l'adresse ci-dessous dans votre navigateur :",
    "All rights reserved.": "Tous droits réservés."
}
```

In `phpunit.xml`, add `<env name="APP_FALLBACK_LOCALE" value="en"/>` next to the `APP_LOCALE` line added by Task 2.

In `.env.example`, change `APP_FALLBACK_LOCALE=fr` to `APP_FALLBACK_LOCALE=en`, and replace the `MAIL_*` block with:

```
# E-mails (lien « mot de passe oublié »). En local, "log" écrit chaque e-mail
# dans storage/logs/laravel.log au lieu de l'envoyer. En production, passer à
# MAIL_MAILER=smtp et renseigner le compte du fournisseur d'e-mails.
MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="ne-pas-repondre@exemple.bj"
MAIL_FROM_NAME="${APP_NAME}"
```

Also set `APP_FALLBACK_LOCALE=en` in the worktree's own `.env` (not committed) so the dev server behaves like the tests.

- [ ] **Step 4: Password rule, email normalization, uniform forgot-password answer**

`app/Providers/AppServiceProvider.php`, add `use Illuminate\Validation\Rules\Password;` and set `boot()` to:

```php
    public function boot(): void
    {
        // Règle de tout mot de passe choisi par un utilisateur (profil, réinitialisation, changement obligatoire).
        Password::defaults(fn () => Password::min(8)->letters()->mixedCase()->numbers());
    }
```

`app/Http/Requests/Auth/LoginRequest.php`, add `use Illuminate\Support\Str;` if missing and this method in the class:

```php
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }
```

`app/Http/Controllers/Auth/PasswordResetLinkController.php`, add `use Illuminate\Support\Str;` and replace `store()`:

```php
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $request->validate(['email' => ['required', 'email']]);

        // Même réponse que l'adresse existe ou non, et même quand l'envoi est
        // limité : la page ne doit pas révéler qui possède un compte.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', __('passwords.sent'));
    }
```

Remove the now-unused `use Illuminate\Validation\ValidationException;` from that controller if Pint flags it.

- [ ] **Step 5: The password field component and the eye**

Create `resources/views/components/champ-mot-de-passe.blade.php`:

```blade
@props(['id', 'name', 'autocomplete' => 'current-password', 'required' => true])
<div class="d-flex gap-2">
    <input id="{{ $id }}" name="{{ $name }}" type="password" autocomplete="{{ $autocomplete }}"
           @required($required) {{ $attributes->merge(['class' => 'field-control']) }}>
    <button type="button" class="btn-outline" data-toggle-password="{{ $id }}" aria-label="Afficher le mot de passe">
        <i class="fas fa-eye"></i>
    </button>
</div>
```

Replace `resources/js/password-toggle.js`:

```js
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-toggle-password]').forEach((bouton) => {
        const champ = document.getElementById(bouton.dataset.togglePassword);
        const icone = bouton.querySelector('i');

        if (!champ || !icone) {
            return;
        }

        bouton.addEventListener('click', () => {
            const masque = champ.type === 'password';
            champ.type = masque ? 'text' : 'password';
            icone.classList.toggle('fa-eye', !masque);
            icone.classList.toggle('fa-eye-slash', masque);
            bouton.setAttribute('aria-label', masque ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });
});
```

In `resources/views/auth/login.blade.php`, replace the whole `<div class="d-flex gap-2">…</div>` that holds the password input and the `js-toggle-password` button with:

```blade
            <x-champ-mot-de-passe id="password" name="password" autocomplete="current-password" placeholder="••••••••" />
```

In `resources/views/auth/reset-password.blade.php`, replace the two password `<input>` elements with `<x-champ-mot-de-passe id="password" name="password" autocomplete="new-password" />` and `<x-champ-mot-de-passe id="password_confirmation" name="password_confirmation" autocomplete="new-password" />`. In `resources/views/auth/confirm-password.blade.php`, replace the password `<input>` with `<x-champ-mot-de-passe id="password" name="password" autocomplete="current-password" />`. Keep the labels and `@error` lines around them.

In `resources/views/auth/forgot-password.blade.php`, replace the explanatory paragraph with:

```blade
    <p class="field-hint mt-2 mb-0">
        Indiquez votre adresse email : si un compte y correspond, vous recevrez un lien pour choisir un nouveau mot de passe.
        En cas de problème, contactez l'administrateur.
    </p>
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Feature/Auth tests/Feature/TraductionsTest.php`
Expected: PASS. If `test_writes_the_password_reset_email_in_French` fails, a `lang/fr.json` key does not match the framework string: copy the exact string from the vendor file named in Step 3.

- [ ] **Step 7: Build, full suite, Pint, commit**

Run: `npm run build`, `php artisan test` (all green), `vendor/bin/pint --dirty`.

```bash
git checkout -- package-lock.json 2>/dev/null; git add -A
git commit -m "$(cat <<'EOF'
fix(auth): working password eye, French messages, safer password reset

The eye button now targets its field by id (the refonte had removed the
container it looked for). Validation and reset emails are in French, a
user-chosen password needs 8 characters with mixed case and digits,
emails are trimmed and lower-cased at login, and "mot de passe oublié"
answers the same way whether the address has an account or not.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: Mot de passe provisoire et changement obligatoire (#3, #18, #19)

**Files:**
- Create: `database/migrations/2026_09_26_000002_add_must_change_password_to_users_table.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`
- Create: `app/Services/GenerateurMotDePasse.php`, `app/Services/ChangementMotDePasse.php`
- Create: `app/Http/Middleware/ExigerChangementMotDePasse.php`
- Modify: `bootstrap/app.php`
- Create: `app/Http/Controllers/Auth/ChangementObligatoireController.php`
- Modify: `routes/auth.php`
- Create: `resources/views/auth/changer-mot-de-passe.blade.php`
- Modify: `app/Http/Controllers/Auth/PasswordController.php`, `app/Http/Controllers/Auth/NewPasswordController.php`
- Modify: `app/Console/Commands/CreateUserCommand.php`
- Create: `app/Console/Commands/ResetPasswordCommand.php`
- Test: create `tests/Unit/Services/GenerateurMotDePasseTest.php`, `tests/Unit/Services/ChangementMotDePasseTest.php`, `tests/Feature/Auth/ChangementObligatoireTest.php`, `tests/Feature/Console/ResetPasswordCommandTest.php`; modify `tests/Feature/Console/CreateUserCommandTest.php`, `tests/Feature/Auth/PasswordResetTest.php`

**Interfaces:**
- Consumes: `Password::defaults()` and `<x-champ-mot-de-passe>` (Task 5); guest layout (Task 4).
- Produces:
  - `users.must_change_password` boolean (false by default), fillable and cast on `User`; factory state `User::factory()->motDePasseProvisoire()`.
  - `App\Services\GenerateurMotDePasse::generer(int $longueur = 10): string` and `GenerateurMotDePasse::regle(): Password` (static, the provisional-password rule).
  - `App\Services\ChangementMotDePasse`:
    - `definir(User $user, string $motDePasse, ?string $sessionAConserver = null): void`: hash, new remember token, flag cleared, every session of the user except `$sessionAConserver` deleted;
    - `definirPourSessionCourante(Request $request, string $motDePasse): void`: same for the logged-in user, keeping the current session and re-issuing the remember-me cookie if this browser had one;
    - `imposerProvisoire(User $user, string $motDePasse): void`: hash, new remember token, flag set, every session of the user deleted.
  - Middleware `ExigerChangementMotDePasse` (web group, after `SynchroniserSeances`): redirects to `password.changer`, or answers 403 to JSON requests, while the flag is set. Allowed routes: `password.changer`, `password.changer.update`, `logout`.
  - Routes `password.changer` (`GET /mot-de-passe/changer`) and `password.changer.update` (`PUT /mot-de-passe/changer`), in the `auth` group of `routes/auth.php`.
  - Command `users:reset-password {email}`.
  - `PasswordController::update()` now flashes `message` (`Mot de passe modifié. Vos autres appareils ont été déconnectés.`) instead of `status`.

Session deletion only applies with the `database` session driver (the app's default); with any other driver `ChangementMotDePasse` just skips it. Tests that check it switch `session.driver` to `database` and insert rows by hand.

- [ ] **Step 1: Generator tests**

Create `tests/Unit/Services/GenerateurMotDePasseTest.php`:

```php
<?php

use App\Services\GenerateurMotDePasse;

it('generates 10-character passwords mixing every character class', function () {
    $generateur = new GenerateurMotDePasse;

    foreach (range(1, 200) as $tour) {
        $motDePasse = $generateur->generer();

        expect(strlen($motDePasse))->toBe(10)
            ->and($motDePasse)->toMatch('/[A-Z]/')
            ->and($motDePasse)->toMatch('/[a-z]/')
            ->and($motDePasse)->toMatch('/[0-9]/')
            ->and($motDePasse)->toMatch('/[^A-Za-z0-9]/');
    }
});

it('passes its own validation rule', function () {
    $motDePasse = (new GenerateurMotDePasse)->generer();

    expect(validator(['p' => $motDePasse], ['p' => GenerateurMotDePasse::regle()])->passes())->toBeTrue();
});

it('rejects a weak password with the provisional rule', function () {
    expect(validator(['p' => 'Motdepasse1'], ['p' => GenerateurMotDePasse::regle()])->passes())->toBeFalse();
});

it('does not repeat itself', function () {
    $generateur = new GenerateurMotDePasse;

    expect(collect(range(1, 50))->map(fn () => $generateur->generer())->unique())->toHaveCount(50);
});
```

Run: `php artisan test tests/Unit/Services/GenerateurMotDePasseTest.php` (expected: FAIL, class missing).

- [ ] **Step 2: Write the generator**

Create `app/Services/GenerateurMotDePasse.php`:

```php
<?php

namespace App\Services;

use Illuminate\Validation\Rules\Password;

/**
 * Mots de passe provisoires transmis par l'admin : 10 caractères avec au
 * moins une majuscule, une minuscule, un chiffre et un caractère spécial.
 * Les caractères ambigus à la lecture (0/O, 1/l/I) sont exclus. Les mêmes
 * jeux sont repris dans resources/js/mot-de-passe-provisoire.js.
 */
class GenerateurMotDePasse
{
    private const JEUX = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnopqrstuvwxyz',
        '23456789',
        '!@#$%&*?-_+=',
    ];

    public static function regle(): Password
    {
        return Password::min(10)->letters()->mixedCase()->numbers()->symbols();
    }

    public function generer(int $longueur = 10): string
    {
        $tous = implode('', self::JEUX);
        $caracteres = array_map(fn (string $jeu) => $jeu[random_int(0, strlen($jeu) - 1)], self::JEUX);

        while (count($caracteres) < $longueur) {
            $caracteres[] = $tous[random_int(0, strlen($tous) - 1)];
        }

        // Mélange de Fisher-Yates avec random_int : shuffle() n'est pas un aléa cryptographique.
        for ($i = count($caracteres) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
        }

        return implode('', $caracteres);
    }
}
```

Run: `php artisan test tests/Unit/Services/GenerateurMotDePasseTest.php` (expected: PASS).

- [ ] **Step 3: Column, model, factory**

Create `database/migrations/2026_09_26_000002_add_must_change_password_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
```

In `app/Models/User.php`, add `'must_change_password'` to `$fillable` and `'must_change_password' => 'boolean',` to `casts()`.

In `database/factories/UserFactory.php`, add this state after `unverified()`:

```php
    /**
     * Compte avec un mot de passe provisoire à changer à la prochaine connexion.
     */
    public function motDePasseProvisoire(): static
    {
        return $this->state(fn (array $attributes) => ['must_change_password' => true]);
    }
```

- [ ] **Step 4: Password-change service tests**

Create `tests/Unit/Services/ChangementMotDePasseTest.php`:

```php
<?php

use App\Models\User;
use App\Services\ChangementMotDePasse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function inserePlusieursSessions(User $user, User $autre): void
{
    DB::table('sessions')->insert([
        ['id' => 'autre-navigateur', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'session-courante', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'autre-compte', 'user_id' => $autre->id, 'payload' => '', 'last_activity' => now()->timestamp],
    ]);
}

beforeEach(fn () => config(['session.driver' => 'database']));

it('sets a chosen password, clears the flag and closes the other sessions', function () {
    $user = User::factory()->motDePasseProvisoire()->create(['remember_token' => 'ancien-jeton']);
    inserePlusieursSessions($user, User::factory()->create());

    app(ChangementMotDePasse::class)->definir($user, 'Nouveau-Pass1', 'session-courante');

    $user->refresh();
    expect(Hash::check('Nouveau-Pass1', $user->password))->toBeTrue();
    expect($user->must_change_password)->toBeFalse();
    expect($user->remember_token)->not->toBe('ancien-jeton');
    expect(DB::table('sessions')->pluck('id')->all())->toEqualCanonicalizing(['session-courante', 'autre-compte']);
});

it('imposes a provisional password and closes every session of the account', function () {
    $user = User::factory()->create();
    inserePlusieursSessions($user, User::factory()->create());

    app(ChangementMotDePasse::class)->imposerProvisoire($user, 'Provisoire-7!');

    $user->refresh();
    expect(Hash::check('Provisoire-7!', $user->password))->toBeTrue();
    expect($user->must_change_password)->toBeTrue();
    expect(DB::table('sessions')->pluck('id')->all())->toBe(['autre-compte']);
});
```

Run: `php artisan test tests/Unit/Services/ChangementMotDePasseTest.php` (expected: FAIL, class missing).

- [ ] **Step 5: Write the service**

Create `app/Services/ChangementMotDePasse.php`:

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seul point d'écriture d'un mot de passe : profil, lien de réinitialisation,
 * changement obligatoire, réinitialisation par l'admin et commande artisan.
 */
class ChangementMotDePasse
{
    /**
     * Mot de passe choisi par l'utilisateur : ses autres sessions sont fermées.
     */
    public function definir(User $user, string $motDePasse, ?string $sessionAConserver = null): void
    {
        $user->forceFill([
            'password' => Hash::make($motDePasse),
            'remember_token' => Str::random(60),
            'must_change_password' => false,
        ])->save();

        $this->fermerSessions($user, $sessionAConserver);
    }

    /**
     * Même chose pour l'utilisateur connecté, qui reste connecté sur ce navigateur.
     */
    public function definirPourSessionCourante(Request $request, string $motDePasse): void
    {
        $user = $request->user();
        $avaitCookieDeRappel = $request->cookies->has(Auth::guard()->getRecallerName());

        $this->definir($user, $motDePasse, $request->session()->getId());

        // Le jeton « se souvenir de moi » vient de changer : on réémet le cookie
        // de ce navigateur pour qu'il reste reconnu, les autres le perdent.
        if ($avaitCookieDeRappel) {
            Auth::guard()->login($user, true);
        }
    }

    /**
     * Mot de passe provisoire posé par l'admin ou la commande : toutes les sessions tombent.
     */
    public function imposerProvisoire(User $user, string $motDePasse): void
    {
        $user->forceFill([
            'password' => Hash::make($motDePasse),
            'remember_token' => Str::random(60),
            'must_change_password' => true,
        ])->save();

        $this->fermerSessions($user, null);
    }

    private function fermerSessions(User $user, ?string $sessionAConserver): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->when($sessionAConserver !== null, fn ($query) => $query->where('id', '!=', $sessionAConserver))
            ->delete();
    }
}
```

Run: `php artisan test tests/Unit/Services/ChangementMotDePasseTest.php` (expected: PASS).

- [ ] **Step 6: Forced-change tests**

Create `tests/Feature/Auth/ChangementObligatoireTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->motDePasseProvisoire()->create([
        'role' => UserRole::Coach,
        'password' => Hash::make('Provisoire-7!'),
    ]);
});

it('sends a user with a provisional password to the change screen', function () {
    $this->actingAs($this->user)->get(route('dashboard'))->assertRedirect(route('password.changer'));
});

it('answers 403 to JSON requests while the change is pending', function () {
    $this->actingAs($this->user)->getJson(route('etat-seances'))->assertForbidden();
});

it('still lets the user log out', function () {
    $this->actingAs($this->user)->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();
});

it('forces the change even when the user comes back through the remember-me cookie', function () {
    $this->user->forceFill(['remember_token' => 'jeton-de-rappel'])->save();

    $this->withCookie(Auth::guard()->getRecallerName(), $this->user->id.'|jeton-de-rappel|'.$this->user->password)
        ->get(route('dashboard'))
        ->assertRedirect(route('password.changer'));
});

it('shows the change screen', function () {
    $this->actingAs($this->user)->get(route('password.changer'))
        ->assertOk()
        ->assertSee('Choisissez votre mot de passe');
});

it('rejects a password that breaks the rule', function () {
    $this->actingAs($this->user)
        ->put(route('password.changer.update'), ['password' => 'court', 'password_confirmation' => 'court'])
        ->assertSessionHasErrors('password');

    expect($this->user->fresh()->must_change_password)->toBeTrue();
});

it('rejects reusing the provisional password', function () {
    $this->actingAs($this->user)
        ->put(route('password.changer.update'), ['password' => 'Provisoire-7!', 'password_confirmation' => 'Provisoire-7!'])
        ->assertSessionHasErrors('password');
});

it('clears the flag and lets the user in once the password is changed', function () {
    $this->actingAs($this->user)
        ->put(route('password.changer.update'), ['password' => 'Nouveau-Pass1', 'password_confirmation' => 'Nouveau-Pass1'])
        ->assertRedirect(route('dashboard'));

    $this->user->refresh();
    expect($this->user->must_change_password)->toBeFalse();
    expect(Hash::check('Nouveau-Pass1', $this->user->password))->toBeTrue();
    $this->actingAs($this->user)->get(route('dashboard'))->assertOk();
});

it('sends a user without a pending change away from the change screen', function () {
    $normal = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($normal)->get(route('password.changer'))->assertRedirect(route('dashboard'));
});
```

Run: `php artisan test tests/Feature/Auth/ChangementObligatoireTest.php` (expected: FAIL, route not defined).

- [ ] **Step 7: Middleware, controller, routes, view**

Create `app/Http/Middleware/ExigerChangementMotDePasse.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExigerChangementMotDePasse
{
    private const ROUTES_AUTORISEES = ['password.changer', 'password.changer.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs(...self::ROUTES_AUTORISEES)) {
            if ($request->expectsJson()) {
                abort(403, 'Changement de mot de passe requis.');
            }

            return redirect()->route('password.changer');
        }

        return $next($request);
    }
}
```

In `bootstrap/app.php`, add `use App\Http\Middleware\ExigerChangementMotDePasse;` and put it after `SynchroniserSeances::class` in the `web(append: [...])` array.

Create `app/Http/Controllers/Auth/ChangementObligatoireController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ChangementMotDePasse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ChangementObligatoireController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.changer-mot-de-passe');
    }

    public function update(Request $request, ChangementMotDePasse $changement): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (Hash::check($validated['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Choisissez un mot de passe différent du mot de passe provisoire.']);
        }

        $changement->definirPourSessionCourante($request, $validated['password']);

        return redirect()->route('dashboard')->with('message', 'Mot de passe enregistré. Bienvenue !');
    }
}
```

In `routes/auth.php`, add `use App\Http\Controllers\Auth\ChangementObligatoireController;` and, inside the `Route::middleware('auth')->group(...)` block:

```php
    Route::get('mot-de-passe/changer', [ChangementObligatoireController::class, 'edit'])
        ->name('password.changer');

    Route::put('mot-de-passe/changer', [ChangementObligatoireController::class, 'update'])
        ->name('password.changer.update');
```

Create `resources/views/auth/changer-mot-de-passe.blade.php` (the guest layout already renders the logo, the clock and the overline):

```blade
<x-guest-layout>
    <h1 class="page-title">Choisissez votre mot de passe</h1>
    <p class="field-hint mt-2 mb-0">
        Votre compte utilise un mot de passe provisoire. Choisissez-en un nouveau pour continuer :
        au moins 8 caractères, avec des majuscules, des minuscules et des chiffres.
    </p>

    <form method="POST" action="{{ route('password.changer.update') }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="password" class="field-label">Nouveau mot de passe</label>
            <x-champ-mot-de-passe id="password" name="password" autocomplete="new-password" />
            @error('password') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="field-label">Confirmer le mot de passe</label>
            <x-champ-mot-de-passe id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
        </div>

        <button type="submit" class="btn-ink w-100 justify-content-center">Enregistrer et continuer</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
        @csrf
        <button type="submit" class="field-hint border-0 bg-transparent p-0">Se déconnecter</button>
    </form>
</x-guest-layout>
```

Run: `php artisan test tests/Feature/Auth/ChangementObligatoireTest.php` (expected: PASS).

- [ ] **Step 8: Every password path goes through the service**

`app/Http/Controllers/Auth/PasswordController.php`, add `use App\Services\ChangementMotDePasse;`, remove `use Illuminate\Support\Facades\Hash;`, and replace `update()`:

```php
    public function update(Request $request, ChangementMotDePasse $changement): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $changement->definirPourSessionCourante($request, $validated['password']);

        return back()->with('message', 'Mot de passe modifié. Vos autres appareils ont été déconnectés.');
    }
```

`app/Http/Controllers/Auth/NewPasswordController.php`, add `use App\Services\ChangementMotDePasse;`, change the signature to `public function store(Request $request, ChangementMotDePasse $changement): RedirectResponse`, and replace the `Password::reset` callback with:

```php
            function (User $user) use ($request, $changement) {
                $changement->definir($user, $request->password);

                event(new PasswordReset($user));
            }
```

Remove the now-unused `Hash` and `Str` imports from that file.

Add to `tests/Feature/Auth/PasswordResetTest.php`:

```php
    public function test_resetting_the_password_clears_a_pending_forced_change(): void
    {
        Notification::fake();
        $user = User::factory()->motDePasseProvisoire()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Nouveau-Pass1',
                'password_confirmation' => 'Nouveau-Pass1',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->assertFalse($user->fresh()->must_change_password);
    }
```

Add to `tests/Feature/Auth/PasswordUpdateTest.php` (add `use Illuminate\Support\Facades\DB;`):

```php
    public function test_changing_the_password_logs_out_the_other_devices(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'autre-appareil', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => 'Nouveau-Pass1',
            'password_confirmation' => 'Nouveau-Pass1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'autre-appareil']);
    }
```

- [ ] **Step 9: Artisan commands**

`app/Console/Commands/CreateUserCommand.php`: add `use App\Services\GenerateurMotDePasse;` (keep `use Illuminate\Support\Str;`), change the signature to `public function handle(GenerateurMotDePasse $generateur): int`, normalize the email in `$data` with `'email' => Str::lower(trim($this->argument('email')))`, and replace the end of `handle()` (from `$password = Str::password(16);` to `return self::SUCCESS;`) with:

```php
        $password = $generateur->generer();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => UserRole::from($data['role']),
            'password' => Hash::make($password),
            'must_change_password' => true,
        ]);

        $this->warn("Compte créé. Mot de passe provisoire (affiché une seule fois, à changer à la première connexion) : {$password}");

        return self::SUCCESS;
```

Create `app/Console/Commands/ResetPasswordCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ChangementMotDePasse;
use App\Services\GenerateurMotDePasse;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ResetPasswordCommand extends Command
{
    protected $signature = 'users:reset-password {email}';

    protected $description = 'Remplace le mot de passe d\'un compte par un mot de passe provisoire, à changer à la prochaine connexion';

    public function handle(GenerateurMotDePasse $generateur, ChangementMotDePasse $changement): int
    {
        $email = Str::lower(trim($this->argument('email')));
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Aucun compte ne correspond à l'adresse {$email}.");

            return self::FAILURE;
        }

        $motDePasse = $generateur->generer();
        $changement->imposerProvisoire($user, $motDePasse);

        $this->warn("Mot de passe provisoire pour {$user->name} (affiché une seule fois) : {$motDePasse}");

        return self::SUCCESS;
    }
}
```

In `tests/Feature/Console/CreateUserCommandTest.php`, in `creates a coach account with a random password`, add at the end:

```php
    expect($user->must_change_password)->toBeTrue();
```

Create `tests/Feature/Console/ResetPasswordCommandTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('gives an account a provisional password to change at next login', function () {
    $user = User::factory()->create(['email' => 'admin@cafab.bj']);

    $this->artisan('users:reset-password', ['email' => ' Admin@CAFAB.bj '])
        ->expectsOutputToContain('Mot de passe provisoire pour')
        ->assertSuccessful();

    $user->refresh();
    expect($user->must_change_password)->toBeTrue();
    expect(Hash::check('password', $user->password))->toBeFalse();
});

it('fails for an unknown address', function () {
    $this->artisan('users:reset-password', ['email' => 'inconnu@cafab.bj'])->assertFailed();
});
```

- [ ] **Step 10: Full suite, Pint, commit**

Run: `php artisan test` (all green) then `vendor/bin/pint --dirty`.

```bash
git checkout -- package-lock.json 2>/dev/null; git add -A
git commit -m "$(cat <<'EOF'
feat(auth): provisional passwords with a forced change at first login

Accounts can carry a must_change_password flag; until the user picks a
new password every page redirects to the change screen, remember-me
cookie included. All password writes go through one service that also
logs out the account's other sessions. users:create now issues a
provisional password and users:reset-password lets anyone, admin
included, recover an account without email.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: Profil : email protégé et écran traduit (#8, #19)

**Files:**
- Modify: `app/Http/Requests/ProfileUpdateRequest.php`
- Modify: `app/Http/Controllers/ProfileController.php`
- Modify: `resources/views/profile/edit.blade.php`, `resources/views/profile/partials/update-profile-information-form.blade.php`, `resources/views/profile/partials/update-password-form.blade.php`
- Modify: `resources/views/layouts/app.blade.php` (flash messages)
- Test: `tests/Feature/ProfileTest.php`

**Interfaces:**
- Consumes: `<x-champ-mot-de-passe>` (Task 5), `PasswordController` flashing `message` (Task 6).
- Produces: changing one's email requires `current_password`; the name alone does not. `ProfileController::update()` flashes `message` (`Profil enregistré.`). The layout's green callout shows `session('message')` only (the raw `status` keys `profile-updated` / `password-updated` were leaking into it).

- [ ] **Step 1: Rewrite the profile tests**

Replace the whole content of `tests/Feature/ProfileTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed_in_french(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Informations du profil')
            ->assertSee('Mot de passe actuel')
            ->assertDontSee('Update Password');
    }

    public function test_the_name_can_be_changed_without_the_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', ['name' => 'Prudence Aïvodji', 'email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('Prudence Aïvodji', $user->fresh()->name);
    }

    public function test_changing_the_email_requires_the_current_password(): void
    {
        $user = User::factory()->create(['email' => 'ancien@cafab.bj']);

        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => 'nouveau@cafab.bj'])
            ->assertSessionHasErrors('current_password');

        $this->assertSame('ancien@cafab.bj', $user->fresh()->email);
    }

    public function test_changing_the_email_with_a_wrong_password_is_refused(): void
    {
        $user = User::factory()->create(['email' => 'ancien@cafab.bj']);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'nouveau@cafab.bj',
            'current_password' => 'mauvais',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame('ancien@cafab.bj', $user->fresh()->email);
    }

    public function test_the_email_is_changed_normalized_with_the_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => '  Nouveau@CAFAB.bj ',
            'current_password' => 'password',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertSame('nouveau@cafab.bj', $user->fresh()->email);
    }

    public function test_an_email_used_by_another_account_is_refused(): void
    {
        User::factory()->create(['email' => 'pris@cafab.bj']);
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'Pris@cafab.bj',
            'current_password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_saving_shows_a_french_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->followingRedirects()
            ->patch('/profile', ['name' => 'Nouveau nom', 'email' => $user->email])
            ->assertSee('Profil enregistré.')
            ->assertDontSee('profile-updated');
    }
}
```

Run: `php artisan test tests/Feature/ProfileTest.php` (expected: FAIL on the email, French and message tests).

- [ ] **Step 2: Request and controller**

Replace `app/Http/Requests/ProfileUpdateRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // L'email sert d'identifiant de connexion : le changer exige le mot de passe actuel.
            'current_password' => [
                Rule::requiredIf(fn () => $this->input('email') !== $this->user()->email),
                'nullable',
                'current_password',
            ],
        ];
    }
}
```

In `app/Http/Controllers/ProfileController.php`, in `update()`, replace `$request->user()->fill($request->validated());` with `$request->user()->fill($request->safe()->only(['name', 'email']));` and the final return with:

```php
        return Redirect::route('profile.edit')->with('message', 'Profil enregistré.');
```

In `resources/views/layouts/app.blade.php`, change the success flash block condition from `@if (session('message') || session('status'))` to `@if (session('message'))` and its text from `{{ session('message') ?? session('status') }}` to `{{ session('message') }}`.

- [ ] **Step 3: French profile views**

`resources/views/profile/edit.blade.php`: replace `{{ __('Profile') }}` with `Mon profil`.

Replace the whole of `resources/views/profile/partials/update-profile-information-form.blade.php` (the email-verification block is removed: `User` does not implement `MustVerifyEmail`, so it could never show):

```blade
<section>
    <header class="mb-3">
        <h2 class="section-title mb-1">Informations du profil</h2>
        <p class="field-hint mb-0">Votre nom et l'adresse email qui vous sert à vous connecter.</p>
    </header>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="mb-3">
            <label for="name" class="field-label">Nom</label>
            <input id="name" name="name" type="text" class="field-control" value="{{ old('name', $user->name) }}" required autocomplete="name">
            @error('name') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="field-label">Email</label>
            <input id="email" name="email" type="email" class="field-control" value="{{ old('email', $user->email) }}" required autocomplete="username">
            @error('email') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="profil_current_password" class="field-label">Mot de passe actuel</label>
            <x-champ-mot-de-passe id="profil_current_password" name="current_password" autocomplete="current-password" :required="false" />
            <p class="field-hint">Obligatoire seulement si vous changez d'adresse email.</p>
            @error('current_password') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn-ink">Enregistrer</button>
    </form>
</section>
```

Replace the whole of `resources/views/profile/partials/update-password-form.blade.php`:

```blade
<section>
    <header class="mb-3">
        <h2 class="section-title mb-1">Mot de passe</h2>
        <p class="field-hint mb-0">
            Au moins 8 caractères, avec des majuscules, des minuscules et des chiffres.
            Changer de mot de passe déconnecte vos autres appareils.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        @method('put')

        <div class="mb-3">
            <label for="update_password_current_password" class="field-label">Mot de passe actuel</label>
            <x-champ-mot-de-passe id="update_password_current_password" name="current_password" autocomplete="current-password" />
            @error('current_password', 'updatePassword') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="update_password_password" class="field-label">Nouveau mot de passe</label>
            <x-champ-mot-de-passe id="update_password_password" name="password" autocomplete="new-password" />
            @error('password', 'updatePassword') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="update_password_password_confirmation" class="field-label">Confirmer le nouveau mot de passe</label>
            <x-champ-mot-de-passe id="update_password_password_confirmation" name="password_confirmation" autocomplete="new-password" />
            @error('password_confirmation', 'updatePassword') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn-ink">Changer le mot de passe</button>
    </form>
</section>
```

- [ ] **Step 4: Run the tests, full suite, commit**

Run: `php artisan test tests/Feature/ProfileTest.php tests/Feature/Auth` (expected: PASS), then `php artisan test` (all green) and `vendor/bin/pint --dirty`.

```bash
git checkout -- package-lock.json 2>/dev/null; git add -A
git commit -m "$(cat <<'EOF'
feat(profil): protect email changes and translate the profile screen

Changing the login email now requires the current password, emails are
normalized, and the profile screen is fully in French. The layout no
longer prints raw status keys such as "profile-updated".

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 8: Droits du coach sur les filles et le planning, navigation par onglets (#10, #11, #15)

**Files:**
- Move: `app/Http/Controllers/Admin/FilleController.php` → `app/Http/Controllers/Registre/FilleController.php`
- Move: `app/Http/Controllers/Admin/FilleImportController.php` → `app/Http/Controllers/Registre/FilleImportController.php`
- Move: `app/Http/Controllers/Admin/PlanningController.php` → `app/Http/Controllers/PlanningController.php`
- Move: `app/Http/Requests/Admin/StoreFilleRequest.php`, `UpdateFilleRequest.php` → `app/Http/Requests/Registre/`
- Move: `app/Http/Requests/Admin/StorePlanningRequest.php`, `UpdatePlanningRequest.php` → `app/Http/Requests/Planning/`
- Move: `resources/views/admin/filles/` → `resources/views/registre/filles/`; `resources/views/admin/plannings/` → `resources/views/plannings/`
- Create: `app/Policies/FillePolicy.php`, `app/Policies/PlanningRepetitionPolicy.php`
- Modify: `routes/web.php`
- Create: `resources/views/components/onglets.blade.php`, `resources/views/components/onglets/registre.blade.php`, `onglets/planning.blade.php`, `onglets/prestations.blade.php`
- Modify: `resources/views/layouts/app.blade.php` (sidebar)
- Modify (tabs): `admin/coaches/index`, `registre/filles/index`, `plannings/index`, `admin/calendrier/index`, `admin/pointages/index`, `seances/create-extraordinaire`, `admin/prestations/index`, `admin/paiements/index` (all under `resources/views/`)
- Modify: `resources/css/_cafab-app.scss`
- Move tests: `tests/Feature/Admin/FilleManagementTest.php` and `FilleImportTest.php` → `tests/Feature/Registre/`; `tests/Feature/Admin/PlanningManagementTest.php` → `tests/Feature/PlanningManagementTest.php`
- Create: `tests/Feature/NavigationTest.php`

**Interfaces:**
- Consumes: `SeanceCycleDeVie::synchroniser()` (Task 2).
- Produces:
  - Routes shared by both roles (`auth` + `role:admin,coach`), URL prefix outside `/admin`:
    - `filles.index|create|store|edit|update|toggle-statut|regenerate-pin|import|import.preview|import.confirm` under `/registre/filles`;
    - `plannings.index|create|store|edit|update|toggle-actif` under `/planning`.
  - `App\Policies\FillePolicy::toggleStatut(User, Fille): bool` (admin only).
  - `App\Policies\PlanningRepetitionPolicy::create(User): bool` (admin, or a coach with a `Coach` profile) and `update(User, PlanningRepetition): bool` (admin, or the créneau's own coach).
  - Components `<x-onglets :onglets="[['libelle' => ..., 'url' => ..., 'actif' => bool], ...]" />`, `<x-onglets.registre />`, `<x-onglets.planning />`, `<x-onglets.prestations />`.
  - Views `registre.filles.*` and `plannings.*` (Task 9 edits `registre/filles/index.blade.php` and `plannings/index.blade.php`).

- [ ] **Step 1: Move the files**

```bash
mkdir -p app/Http/Controllers/Registre app/Http/Requests/Registre app/Http/Requests/Planning resources/views/registre tests/Feature/Registre
git mv app/Http/Controllers/Admin/FilleController.php app/Http/Controllers/Registre/FilleController.php
git mv app/Http/Controllers/Admin/FilleImportController.php app/Http/Controllers/Registre/FilleImportController.php
git mv app/Http/Controllers/Admin/PlanningController.php app/Http/Controllers/PlanningController.php
git mv app/Http/Requests/Admin/StoreFilleRequest.php app/Http/Requests/Registre/StoreFilleRequest.php
git mv app/Http/Requests/Admin/UpdateFilleRequest.php app/Http/Requests/Registre/UpdateFilleRequest.php
git mv app/Http/Requests/Admin/StorePlanningRequest.php app/Http/Requests/Planning/StorePlanningRequest.php
git mv app/Http/Requests/Admin/UpdatePlanningRequest.php app/Http/Requests/Planning/UpdatePlanningRequest.php
git mv resources/views/admin/filles resources/views/registre/filles
git mv resources/views/admin/plannings resources/views/plannings
git mv tests/Feature/Admin/FilleManagementTest.php tests/Feature/Registre/FilleManagementTest.php
git mv tests/Feature/Admin/FilleImportTest.php tests/Feature/Registre/FilleImportTest.php
git mv tests/Feature/Admin/PlanningManagementTest.php tests/Feature/PlanningManagementTest.php
```

Then update namespaces and names:
- `app/Http/Controllers/Registre/FilleController.php` and `FilleImportController.php`: `namespace App\Http\Controllers\Registre;`, add `use App\Http\Controllers\Controller;`, use `App\Http\Requests\Registre\StoreFilleRequest` / `UpdateFilleRequest`, views `registre.filles.*` instead of `admin.filles.*`, redirects `route('filles.index')` instead of `route('admin.filles.index')`.
- `app/Http/Requests/Registre/*.php`: `namespace App\Http\Requests\Registre;`. `app/Http/Requests/Planning/*.php`: `namespace App\Http\Requests\Planning;`.
- In every Blade file under `resources/views/registre/filles/` and `resources/views/plannings/`, and in `resources/views/layouts/app.blade.php`, replace `route('admin.filles.` with `route('filles.` and `route('admin.plannings.` with `route('plannings.`.
- In the three moved test files, replace `route('admin.filles.` with `route('filles.` and `route('admin.plannings.` with `route('plannings.`.

Check: `grep -rn "admin\.filles\|admin\.plannings\|admin/filles\|admin/plannings\|Admin\\\\FilleController\|Admin\\\\PlanningController" app resources routes tests` must only match `routes/web.php` (fixed in Step 3).

- [ ] **Step 2: Write the rights tests**

In `tests/Feature/Registre/FilleManagementTest.php`, delete the test `blocks a coach from the registre des filles` and add (import `App\Models\Coach` at the top):

```php
it('lets a coach list the filles', function () {
    $coach = Coach::factory()->create();
    Fille::factory()->count(2)->create();

    $this->actingAs($coach->user)->get(route('filles.index'))->assertOk();
});

it('lets a coach add a fille', function () {
    $coach = Coach::factory()->create();

    $this->actingAs($coach->user)->post(route('filles.store'), [
        'nom' => 'Adjovi',
        'prenom' => 'Chimène',
        'date_entree' => '2026-09-01',
    ])->assertRedirect(route('filles.index'));

    expect(Fille::where('nom', 'Adjovi')->exists())->toBeTrue();
});

it('lets a coach update a fille and regenerate her pin', function () {
    $coach = Coach::factory()->create();
    $fille = Fille::factory()->create(['pin' => '9999']);

    $this->actingAs($coach->user)->put(route('filles.update', $fille), [
        'nom' => $fille->nom,
        'prenom' => 'Grâce',
        'date_entree' => $fille->date_entree->format('Y-m-d'),
    ])->assertRedirect(route('filles.index'));
    $this->actingAs($coach->user)->patch(route('filles.regenerate-pin', $fille));

    expect($fille->fresh()->prenom)->toBe('Grâce');
    expect($fille->fresh()->pin)->not->toBe('9999');
});

it('refuses to let a coach deactivate a fille', function () {
    $coach = Coach::factory()->create();
    $fille = Fille::factory()->create(['statut' => 'actif']);

    $this->actingAs($coach->user)->patch(route('filles.toggle-statut', $fille))->assertForbidden();

    expect($fille->fresh()->statut)->toBe(StatutPersonne::Actif);
});

it('does not offer the deactivation to a coach', function () {
    $coach = Coach::factory()->create();
    Fille::factory()->create(['statut' => 'actif']);

    $this->actingAs($coach->user)->get(route('filles.index'))->assertDontSee('Désactiver');
});
```

In `tests/Feature/Registre/FilleImportTest.php`, delete the test `blocks a coach from importing` (coaches may import now) and add (import `App\Models\Coach`):

```php
it('lets a coach open the Excel import', function () {
    $coach = Coach::factory()->create();

    $this->actingAs($coach->user)->get(route('filles.import'))->assertOk();
});
```

In `tests/Feature/PlanningManagementTest.php`, delete `blocks a coach from managing plannings` and add:

```php
it('lets a coach see the whole planning', function () {
    $coach = Coach::factory()->create();
    PlanningRepetition::factory()->count(2)->create();

    $this->actingAs($coach->user)->get(route('plannings.index'))->assertOk();
});

it('forces a coach\'s new créneau onto himself, whatever coach_id is sent', function () {
    $coach = Coach::factory()->create();
    $autre = Coach::factory()->create();

    $this->actingAs($coach->user)->post(route('plannings.store'), [
        'jour_semaine' => 1,
        'heure_debut' => '17:00',
        'coach_id' => $autre->id,
    ])->assertRedirect(route('plannings.index'));

    expect(PlanningRepetition::where('coach_id', $coach->id)->where('jour_semaine', 1)->exists())->toBeTrue();
    expect(PlanningRepetition::where('coach_id', $autre->id)->exists())->toBeFalse();
});

it('lets a coach edit and deactivate his own créneau', function () {
    $coach = Coach::factory()->create();
    $planning = PlanningRepetition::factory()->create(['coach_id' => $coach->id, 'actif' => true]);

    $this->actingAs($coach->user)->get(route('plannings.edit', $planning))->assertOk();
    $this->actingAs($coach->user)->patch(route('plannings.toggle-actif', $planning));

    expect($planning->fresh()->actif)->toBeFalse();
});

it('refuses to let a coach touch another coach\'s créneau', function () {
    $coach = Coach::factory()->create();
    $planning = PlanningRepetition::factory()->create(['heure_debut' => '17:00:00']);

    $this->actingAs($coach->user)->get(route('plannings.edit', $planning))->assertForbidden();
    $this->actingAs($coach->user)->put(route('plannings.update', $planning), [
        'jour_semaine' => $planning->jour_semaine->value,
        'heure_debut' => '19:00',
    ])->assertForbidden();
    $this->actingAs($coach->user)->patch(route('plannings.toggle-actif', $planning))->assertForbidden();

    expect($planning->fresh()->heure_debut)->toBe('17:00:00');
});

it('refuses a créneau from a coach account without a coach profile', function () {
    $sansProfil = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($sansProfil)->post(route('plannings.store'), [
        'jour_semaine' => 1,
        'heure_debut' => '17:00',
    ])->assertForbidden();
});

it('still requires the admin to choose a coach', function () {
    $this->actingAs($this->admin)->post(route('plannings.store'), [
        'jour_semaine' => 1,
        'heure_debut' => '17:00',
    ])->assertSessionHasErrors('coach_id');
});
```

Create `tests/Feature/NavigationTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\User;

it('gives the coach Registre and Planning, without the admin sections', function () {
    $coach = Coach::factory()->create();

    $this->actingAs($coach->user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('filles.index'), false)
        ->assertSee(route('plannings.index'), false)
        ->assertDontSee(route('admin.prestations.index'), false);
});

it('shows the admin every Planning tab, including Séance extraordinaire', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('plannings.index'))
        ->assertOk()
        ->assertSee(route('admin.calendrier'), false)
        ->assertSee(route('admin.pointages.index'), false)
        ->assertSee(route('seances.create-extraordinaire'), false);
});

it('shows the coach only the Planning tabs he can use', function () {
    $coach = Coach::factory()->create();

    $this->actingAs($coach->user)->get(route('plannings.index'))
        ->assertOk()
        ->assertSee(route('seances.create-extraordinaire'), false)
        ->assertDontSee(route('admin.calendrier'), false);
});

it('shows the coach only the Filles tab of the Registre', function () {
    $coach = Coach::factory()->create();

    $this->actingAs($coach->user)->get(route('filles.index'))
        ->assertOk()
        ->assertDontSee(route('admin.coaches.index'), false);
});

it('shows the admin the Coachs and Filles tabs', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('admin.coaches.index'))
        ->assertOk()
        ->assertSee(route('filles.index'), false);
});
```

Run: `php artisan test tests/Feature/Registre tests/Feature/PlanningManagementTest.php tests/Feature/NavigationTest.php`
Expected: FAIL (routes `filles.*` and `plannings.*` are not defined yet).

- [ ] **Step 3: Routes**

In `routes/web.php`:
- remove the imports of `App\Http\Controllers\Admin\FilleController`, `Admin\FilleImportController` and `Admin\PlanningController`, and add:

```php
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\Registre\FilleController;
use App\Http\Controllers\Registre\FilleImportController;
```

- remove from the `admin.` group every `filles` and `plannings` route (the `Route::resource('filles', ...)`, its two `patch` routes, the three `filles/import...` routes, the `Route::resource('plannings', ...)` and `plannings/{planning}/toggle-actif`);
- make the existing `Route::middleware(['auth', 'role:admin,coach'])->group(...)` block read:

```php
Route::middleware(['auth', 'role:admin,coach'])->group(function () {
    Route::get('seances/extraordinaire/creer', [SeanceController::class, 'create'])->name('seances.create-extraordinaire');
    Route::post('seances/extraordinaire', [SeanceController::class, 'store'])->name('seances.store-extraordinaire');

    Route::get('etat-seances', EtatSeancesController::class)->name('etat-seances');

    Route::prefix('registre')->group(function () {
        Route::get('filles/import', [FilleImportController::class, 'form'])->name('filles.import');
        Route::post('filles/import/preview', [FilleImportController::class, 'preview'])->name('filles.import.preview');
        Route::post('filles/import/confirm', [FilleImportController::class, 'confirm'])->name('filles.import.confirm');

        Route::resource('filles', FilleController::class)->except(['show', 'destroy']);
        Route::patch('filles/{fille}/toggle-statut', [FilleController::class, 'toggleStatut'])->name('filles.toggle-statut');
        Route::patch('filles/{fille}/regenerate-pin', [FilleController::class, 'regeneratePin'])->name('filles.regenerate-pin');
    });

    Route::resource('planning', PlanningController::class)->except(['show', 'destroy'])->names('plannings');
    Route::patch('planning/{planning}/toggle-actif', [PlanningController::class, 'toggleActif'])->name('plannings.toggle-actif');
});
```

- [ ] **Step 4: Policies and controller rules**

Create `app/Policies/FillePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Fille;
use App\Models\User;

/**
 * Admin et coach gèrent les filles ensemble ; seule la désactivation reste à l'admin.
 */
class FillePolicy
{
    public function toggleStatut(User $user, Fille $fille): bool
    {
        return $user->role === UserRole::Admin;
    }
}
```

Create `app/Policies/PlanningRepetitionPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PlanningRepetition;
use App\Models\User;

/**
 * Le coach voit tout le planning mais n'agit que sur ses propres créneaux.
 */
class PlanningRepetitionPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin || $user->coach !== null;
    }

    public function update(User $user, PlanningRepetition $planning): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->coach !== null && $planning->coach_id === $user->coach->id;
    }
}
```

In `app/Http/Controllers/Registre/FilleController.php`, add `use Illuminate\Support\Facades\Gate;` and make `Gate::authorize('toggleStatut', $fille);` the first line of `toggleStatut()`.

In both `app/Http/Requests/Planning/StorePlanningRequest.php` and `UpdatePlanningRequest.php`, add `use App\Enums\UserRole;` and `use Illuminate\Validation\Rule;` and replace the `coach_id` rule with:

```php
            // Le coach ne choisit pas : ses créneaux sont toujours à son nom (voir PlanningController).
            'coach_id' => [Rule::requiredIf(fn () => $this->user()->role === UserRole::Admin), 'nullable', 'exists:coaches,id'],
```

Replace `app/Http/Controllers/PlanningController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Planning\StorePlanningRequest;
use App\Http\Requests\Planning\UpdatePlanningRequest;
use App\Models\Coach;
use App\Models\PlanningRepetition;
use App\Models\Seance;
use App\Services\SeanceCycleDeVie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function __construct(private SeanceCycleDeVie $cycleDeVie) {}

    public function index(): View
    {
        $plannings = PlanningRepetition::with('coach.user')->orderBy('jour_semaine')->orderBy('heure_debut')->get();

        return view('plannings.index', compact('plannings'));
    }

    public function create(): View
    {
        Gate::authorize('create', PlanningRepetition::class);

        $coaches = Coach::with('user')->where('statut', 'actif')->get();

        return view('plannings.create', compact('coaches'));
    }

    public function store(StorePlanningRequest $request): RedirectResponse
    {
        Gate::authorize('create', PlanningRepetition::class);

        PlanningRepetition::create([
            'jour_semaine' => $request->validated('jour_semaine'),
            'heure_debut' => $request->validated('heure_debut'),
            'coach_id' => $this->coachReferent($request),
        ]);

        $this->cycleDeVie->synchroniser();

        return redirect()->route('plannings.index')->with('message', 'Créneau ajouté.');
    }

    public function edit(PlanningRepetition $planning): View
    {
        Gate::authorize('update', $planning);

        $coaches = Coach::with('user')->where('statut', 'actif')->get();

        return view('plannings.edit', compact('planning', 'coaches'));
    }

    public function update(UpdatePlanningRequest $request, PlanningRepetition $planning): RedirectResponse
    {
        Gate::authorize('update', $planning);

        $planning->update([
            'jour_semaine' => $request->validated('jour_semaine'),
            'heure_debut' => $request->validated('heure_debut'),
            'coach_id' => $this->coachReferent($request),
        ]);

        $this->reconcilerSeancesFutures($planning);

        return redirect()->route('plannings.index')->with('message', 'Créneau mis à jour.');
    }

    public function toggleActif(PlanningRepetition $planning): RedirectResponse
    {
        Gate::authorize('update', $planning);

        $planning->update(['actif' => ! $planning->actif]);

        $this->reconcilerSeancesFutures($planning);

        return redirect()->route('plannings.index')->with('message', 'Statut du créneau mis à jour.');
    }

    /**
     * Un coach ne crée et ne modifie que ses propres créneaux, quel que soit le coach_id envoyé.
     */
    private function coachReferent(Request $request): int
    {
        return $request->user()->role === UserRole::Admin
            ? (int) $request->input('coach_id')
            : $request->user()->coach->id;
    }

    /**
     * SeanceGenerator ignore toute date qui a déjà une séance pour ce créneau :
     * après un changement d'heure ou une désactivation, les séances futures « à
     * venir » (sans pointage possible) sont supprimées puis régénérées tout de suite.
     */
    private function reconcilerSeancesFutures(PlanningRepetition $planning): void
    {
        Seance::where('planning_repetition_id', $planning->id)
            ->where('date', '>', today())
            ->where('statut', 'a_venir')
            ->delete();

        $this->cycleDeVie->synchroniser();
    }
}
```

In `resources/views/plannings/create.blade.php` and `edit.blade.php`, wrap the coach `<select>` block (label, select and its `@error`) in `@if (auth()->user()->role === \App\Enums\UserRole::Admin) ... @else ... @endif`, the `@else` branch being:

```blade
                        <div class="mb-4">
                            <span class="field-label">Coach référent</span>
                            <input type="text" class="field-control" value="{{ auth()->user()->name }}" disabled>
                            <p class="field-hint">Vos créneaux sont toujours à votre nom.</p>
                        </div>
```

In `resources/views/plannings/index.blade.php`: wrap the header button "Ajouter un créneau" in `@can('create', \App\Models\PlanningRepetition::class) ... @endcan`; wrap the content of the Actions `<td>` in `@can('update', $planning) ... @else <span class="field-hint">—</span> @endcan`.

In `resources/views/registre/filles/index.blade.php`: wrap the Désactiver/Réactiver `<form>` in `@can('toggleStatut', $fille) ... @endcan`.

- [ ] **Step 5: Tabs and sidebar**

Create `resources/views/components/onglets.blade.php`:

```blade
@props(['onglets'])
<nav class="onglets mb-4" aria-label="Sous-navigation">
    @foreach ($onglets as $onglet)
        <a href="{{ $onglet['url'] }}" @class(['onglet', 'active' => $onglet['actif']]) @if ($onglet['actif']) aria-current="page" @endif>
            {{ $onglet['libelle'] }}
        </a>
    @endforeach
</nav>
```

Create `resources/views/components/onglets/registre.blade.php`:

```blade
@php
    $onglets = [];
    if (auth()->user()->role === \App\Enums\UserRole::Admin) {
        $onglets[] = ['libelle' => 'Coachs', 'url' => route('admin.coaches.index'), 'actif' => request()->routeIs('admin.coaches.*')];
    }
    $onglets[] = ['libelle' => 'Filles', 'url' => route('filles.index'), 'actif' => request()->routeIs('filles.*')];
@endphp
<x-onglets :onglets="$onglets" />
```

Create `resources/views/components/onglets/planning.blade.php`:

```blade
@php
    $estAdmin = auth()->user()->role === \App\Enums\UserRole::Admin;
    $onglets = [['libelle' => 'Planning récurrent', 'url' => route('plannings.index'), 'actif' => request()->routeIs('plannings.*')]];
    if ($estAdmin) {
        $onglets[] = ['libelle' => 'Calendrier', 'url' => route('admin.calendrier'), 'actif' => request()->routeIs('admin.calendrier')];
        $onglets[] = ['libelle' => 'Pointages', 'url' => route('admin.pointages.index'), 'actif' => request()->routeIs('admin.pointages.*')];
    }
    $onglets[] = ['libelle' => 'Séance extraordinaire', 'url' => route('seances.create-extraordinaire'), 'actif' => request()->routeIs('seances.create-extraordinaire')];
@endphp
<x-onglets :onglets="$onglets" />
```

Create `resources/views/components/onglets/prestations.blade.php`:

```blade
<x-onglets :onglets="[
    ['libelle' => 'Prestations', 'url' => route('admin.prestations.index'), 'actif' => request()->routeIs('admin.prestations.*')],
    ['libelle' => 'Paiements', 'url' => route('admin.paiements.index'), 'actif' => request()->routeIs('admin.paiements.*')],
]" />
```

Insert the tabs as the first element after the `</x-slot>` of the header in each page:
- `<x-onglets.registre />` in `admin/coaches/index.blade.php` and `registre/filles/index.blade.php`;
- `<x-onglets.planning />` in `plannings/index.blade.php`, `admin/calendrier/index.blade.php`, `admin/pointages/index.blade.php`, `seances/create-extraordinaire.blade.php`;
- `<x-onglets.prestations />` in `admin/prestations/index.blade.php` and `admin/paiements/index.blade.php`.

Append to `resources/css/_cafab-app.scss`:

```scss
/* Onglets internes des sections Registre, Planning, Prestations & cachets */
.onglets {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
  border-bottom: 1px solid var(--border);
}

.onglet {
  padding: 10px 16px;
  margin-bottom: -1px;
  font-weight: 600;
  font-size: 14px;
  color: var(--ink-3);
  text-decoration: none;
  border-bottom: 3px solid transparent;
}

.onglet:hover {
  color: var(--ink);
}

.onglet.active {
  color: var(--ink);
  border-bottom-color: var(--accent);
}
```

In `resources/views/layouts/app.blade.php`, replace the content of `<nav class="d-flex flex-column gap-1">` with:

```blade
                @php($estAdmin = auth()->user()->role === \App\Enums\UserRole::Admin)

                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    Tableau de bord
                </a>

                @unless ($estAdmin)
                    <a href="{{ route('coach.seance') }}" class="nav-link {{ request()->routeIs('coach.seance') ? 'active' : '' }}">
                        Ma répétition
                    </a>
                    <a href="{{ route('coach.historique') }}" class="nav-link {{ request()->routeIs('coach.historique') ? 'active' : '' }}">
                        Mon historique
                    </a>
                @endunless

                <a href="{{ $estAdmin ? route('admin.coaches.index') : route('filles.index') }}"
                   class="nav-link {{ request()->routeIs(['admin.coaches.*', 'filles.*']) ? 'active' : '' }}">
                    Registre
                </a>
                <a href="{{ route('plannings.index') }}"
                   class="nav-link {{ request()->routeIs(['plannings.*', 'admin.calendrier', 'admin.pointages.*', 'seances.create-extraordinaire']) ? 'active' : '' }}">
                    Planning
                </a>

                @if ($estAdmin)
                    <a href="{{ route('admin.prestations.index') }}" class="nav-link {{ request()->routeIs(['admin.prestations.*', 'admin.paiements.*']) ? 'active' : '' }}">
                        Prestations &amp; cachets
                    </a>
                    <a href="{{ route('admin.rapports.index') }}" class="nav-link {{ request()->routeIs('admin.rapports.*') ? 'active' : '' }}">
                        Rapports
                    </a>
                @endif

                <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}">
                    Mon profil
                </a>
```

- [ ] **Step 6: Run the tests, build, full suite, commit**

Run: `php artisan test tests/Feature/Registre tests/Feature/PlanningManagementTest.php tests/Feature/NavigationTest.php` (expected: PASS), `npm run build`, `php artisan test` (all green), `vendor/bin/pint --dirty`.

```bash
git checkout -- package-lock.json 2>/dev/null; git add -A
git commit -m "$(cat <<'EOF'
feat(droits): coaches manage filles and their own créneaux

The Filles and Planning pages are now shared by admin and coach, with
policies keeping deactivation to the admin and restricting a coach to
his own créneaux. Registre, Planning and Prestations get internal tabs,
which also brings back the links to Filles and Séance extraordinaire
that the refonte had dropped from the menu.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 9: Comptes des coachs côté admin et menus « … » (#3, #5, #6, #18)

**Files:**
- Modify: `app/Http/Controllers/Admin/CoachController.php`
- Modify: `app/Http/Requests/Admin/StoreCoachRequest.php`, `app/Http/Requests/Admin/UpdateCoachRequest.php`
- Modify: `routes/web.php`
- Modify: `resources/views/admin/coaches/create.blade.php`, `edit.blade.php`, `index.blade.php`
- Create: `resources/views/components/menu-actions.blade.php`
- Modify: `resources/views/registre/filles/index.blade.php`, `resources/views/plannings/index.blade.php`
- Modify: `resources/views/layouts/app.blade.php` (shared confirmation modal)
- Create: `resources/js/confirmation.js`, `resources/js/copier.js`, `resources/js/mot-de-passe-provisoire.js`
- Modify: `resources/js/app.js`, `resources/css/_cafab-app.scss`
- Test: `tests/Feature/Admin/CoachManagementTest.php`, `tests/Feature/Registre/FilleManagementTest.php`

**Interfaces:**
- Consumes: `GenerateurMotDePasse::generer()` and `::regle()`, `ChangementMotDePasse::imposerProvisoire()` (Task 6); `<x-champ-mot-de-passe>` (Task 5); policies `toggleStatut` / `update` (Task 8).
- Produces:
  - Route `admin.coaches.reset-password` (`PATCH /admin/coaches/{coach}/reset-password`).
  - Session flash `identifiants` = `['nom' => string, 'email' => string, 'mot_de_passe' => string]`, displayed once on the coach list.
  - `<x-menu-actions>` (a "…" Bootstrap dropdown; the slot holds `<li>` items). Used by Task 10.
  - Any `<form data-confirmer="Question ?">` asks for confirmation in the shared modal `#modale-confirmation` before submitting.
  - `data-copier="texte"` or `data-copier-champ="id-du-champ"` copies to the clipboard; `data-generer-mot-de-passe="id-du-champ"` fills a field with a new provisional password.

`.table-card` has `overflow: hidden`, which would clip an ordinary dropdown. The "…" button therefore passes `data-bs-popper-config='{"strategy":"fixed"}'`, which positions the menu relative to the window.

- [ ] **Step 1: Write the tests**

In `tests/Feature/Admin/CoachManagementTest.php` (add `use App\Services\GenerateurMotDePasse;` and `use Illuminate\Support\Facades\Hash;`):

Replace the test `lets the admin create a coach with a generated pin` with:

```php
it('lets the admin create a coach with a provisional password to change at first login', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.coaches.store'), [
        'name' => 'Prudence Aïvodji',
        'email' => '  Prudence@CAFAB.bj ',
        'password' => 'Provisoire-7!',
        'contact' => '+229 01 00 00 00 00',
        'date_entree' => '2026-01-15',
    ]);

    $response->assertRedirect(route('admin.coaches.index'));
    $response->assertSessionHas('identifiants', [
        'nom' => 'Prudence Aïvodji',
        'email' => 'prudence@cafab.bj',
        'mot_de_passe' => 'Provisoire-7!',
    ]);

    $user = User::where('email', 'prudence@cafab.bj')->firstOrFail();
    expect($user->role)->toBe(UserRole::Coach);
    expect($user->must_change_password)->toBeTrue();
    expect(Hash::check('Provisoire-7!', $user->password))->toBeTrue();
    expect($user->coach->pin)->toMatch('/^\d{4}$/');
    expect($user->coach->statut)->toBe(StatutPersonne::Actif);
});

it('pre-fills the creation form with a compliant provisional password', function () {
    $this->actingAs($this->admin)->get(route('admin.coaches.create'))
        ->assertOk()
        ->assertViewHas('motDePasse', fn (string $motDePasse) => validator(['p' => $motDePasse], ['p' => GenerateurMotDePasse::regle()])->passes());
});

it('refuses a weak provisional password', function () {
    $this->actingAs($this->admin)->post(route('admin.coaches.store'), [
        'name' => 'Test',
        'email' => 'test@cafab.bj',
        'password' => 'motdepasse',
        'date_entree' => '2026-01-15',
    ])->assertSessionHasErrors('password');
});

it('shows the credentials to hand over once, on the list', function () {
    $this->actingAs($this->admin)
        ->withSession(['identifiants' => ['nom' => 'Prudence', 'email' => 'prudence@cafab.bj', 'mot_de_passe' => 'Provisoire-7!']])
        ->get(route('admin.coaches.index'))
        ->assertSee('Identifiants à transmettre')
        ->assertSee('Provisoire-7!');
});
```

Replace the test `lets the admin update a coach contact` with:

```php
it('lets the admin edit every field of a coach', function () {
    $coach = Coach::factory()->create(['contact' => '+229 00 00 00 00 00']);

    $this->actingAs($this->admin)->put(route('admin.coaches.update', $coach), [
        'name' => 'Maurice Gnonlonfoun',
        'email' => ' Maurice@CAFAB.bj',
        'contact' => '+229 11 11 11 11 11',
        'date_entree' => '2025-10-01',
    ])->assertRedirect(route('admin.coaches.index'));

    $coach->refresh();
    expect($coach->user->name)->toBe('Maurice Gnonlonfoun');
    expect($coach->user->email)->toBe('maurice@cafab.bj');
    expect($coach->contact)->toBe('+229 11 11 11 11 11');
    expect($coach->date_entree->format('Y-m-d'))->toBe('2025-10-01');
});

it('refuses an email already used by another account, but accepts keeping one\'s own', function () {
    User::factory()->create(['email' => 'pris@cafab.bj']);
    $coach = Coach::factory()->create();
    $donnees = ['name' => $coach->user->name, 'date_entree' => '2026-01-15'];

    $this->actingAs($this->admin)->put(route('admin.coaches.update', $coach), [...$donnees, 'email' => 'Pris@cafab.bj'])
        ->assertSessionHasErrors('email');
    $this->actingAs($this->admin)->put(route('admin.coaches.update', $coach), [...$donnees, 'email' => $coach->user->email])
        ->assertSessionHasNoErrors();
});

it('lets the admin reset a coach password', function () {
    $coach = Coach::factory()->create();

    $response = $this->actingAs($this->admin)->patch(route('admin.coaches.reset-password', $coach));

    $response->assertRedirect(route('admin.coaches.index'));
    $motDePasse = session('identifiants')['mot_de_passe'];
    expect(Hash::check($motDePasse, $coach->user->fresh()->password))->toBeTrue();
    expect($coach->user->fresh()->must_change_password)->toBeTrue();
});

it('groups the row actions in a menu with confirmations', function () {
    Coach::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.coaches.index'))
        ->assertSee('Réinitialiser le mot de passe')
        ->assertSee('data-confirmer', false)
        ->assertSee('modale-confirmation', false);
});

it('forbids the reset to a coach', function () {
    $coach = Coach::factory()->create();

    $this->actingAs($coach->user)->patch(route('admin.coaches.reset-password', $coach))->assertForbidden();
});
```

In `tests/Feature/Registre/FilleManagementTest.php`, add:

```php
it('offers the admin the deactivation in the row menu', function () {
    Fille::factory()->create(['statut' => 'actif']);

    $this->actingAs($this->admin)->get(route('filles.index'))
        ->assertSee('Désactiver')
        ->assertSee('Régénérer le PIN');
});
```

Run: `php artisan test tests/Feature/Admin/CoachManagementTest.php tests/Feature/Registre/FilleManagementTest.php`
Expected: FAIL (no password field, no reset route, no menu).

- [ ] **Step 2: Requests, controller, route**

Replace `app/Http/Requests/Admin/StoreCoachRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Services\GenerateurMotDePasse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', GenerateurMotDePasse::regle()],
            'contact' => ['nullable', 'string', 'max:50'],
            'date_entree' => ['required', 'date'],
        ];
    }
}
```

Replace `app/Http/Requests/Admin/UpdateCoachRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('coach')->user_id)],
            'contact' => ['nullable', 'string', 'max:50'],
            'date_entree' => ['required', 'date'],
        ];
    }
}
```

In `app/Http/Controllers/Admin/CoachController.php`, add `use App\Services\ChangementMotDePasse;` and `use App\Services\GenerateurMotDePasse;`, remove `use Illuminate\Support\Str;`, and replace `create()`, `store()` and `update()`, then add `resetPassword()`:

```php
    public function create(GenerateurMotDePasse $generateur): View
    {
        return view('admin.coaches.create', ['motDePasse' => $generateur->generer()]);
    }

    public function store(StoreCoachRequest $request, PinGenerator $pinGenerator): RedirectResponse
    {
        DB::transaction(function () use ($request, $pinGenerator) {
            $user = User::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'role' => UserRole::Coach,
                'password' => Hash::make($request->validated('password')),
                'must_change_password' => true,
            ]);

            Coach::create([
                'user_id' => $user->id,
                'pin' => $pinGenerator->generate(),
                'contact' => $request->string('contact')->value() ?: null,
                'date_entree' => $request->date('date_entree'),
            ]);
        });

        return redirect()->route('admin.coaches.index')
            ->with('message', 'Coach ajouté.')
            ->with('identifiants', [
                'nom' => $request->validated('name'),
                'email' => $request->validated('email'),
                'mot_de_passe' => $request->validated('password'),
            ]);
    }

    public function update(UpdateCoachRequest $request, Coach $coach): RedirectResponse
    {
        DB::transaction(function () use ($request, $coach) {
            $coach->user->update([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
            ]);

            $coach->update([
                'contact' => $request->string('contact')->value() ?: null,
                'date_entree' => $request->date('date_entree'),
            ]);
        });

        return redirect()->route('admin.coaches.index')->with('message', 'Coach mis à jour.');
    }

    public function resetPassword(Coach $coach, GenerateurMotDePasse $generateur, ChangementMotDePasse $changement): RedirectResponse
    {
        $motDePasse = $generateur->generer();
        $changement->imposerProvisoire($coach->user, $motDePasse);

        return redirect()->route('admin.coaches.index')
            ->with('message', 'Mot de passe réinitialisé. Les sessions du coach ont été fermées.')
            ->with('identifiants', [
                'nom' => $coach->user->name,
                'email' => $coach->user->email,
                'mot_de_passe' => $motDePasse,
            ]);
    }
```

In `routes/web.php`, in the `admin.` group, after the `coaches/{coach}/regenerate-pin` route:

```php
    Route::patch('coaches/{coach}/reset-password', [CoachController::class, 'resetPassword'])
        ->name('coaches.reset-password');
```

- [ ] **Step 3: Coach forms**

Replace the whole of `resources/views/admin/coaches/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Ajouter un coach</h1>
            <p class="field-hint mb-0">Un mot de passe provisoire est proposé : le coach devra le changer à sa première connexion.</p>
        </div>
    </x-slot>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="content-card">
                <form action="{{ route('admin.coaches.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="field-label">Nom complet</label>
                        <input id="name" name="name" type="text" class="field-control" value="{{ old('name') }}" required>
                        @error('name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="field-label">E-mail</label>
                        <input id="email" name="email" type="email" class="field-control" value="{{ old('email') }}" required>
                        @error('email') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="field-label">Mot de passe provisoire</label>
                        <x-champ-mot-de-passe id="password" name="password" autocomplete="new-password" value="{{ $motDePasse }}" class="mono" />
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn-outline btn-sm" data-generer-mot-de-passe="password">Générer</button>
                            <button type="button" class="btn-outline btn-sm" data-copier-champ="password">Copier</button>
                        </div>
                        <p class="field-hint">10 caractères au moins, avec majuscule, minuscule, chiffre et caractère spécial.</p>
                        @error('password') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="contact" class="field-label">Contact</label>
                        <input id="contact" name="contact" type="text" class="field-control" value="{{ old('contact') }}">
                    </div>

                    <div class="mb-4">
                        <label for="date_entree" class="field-label">Date d'entrée</label>
                        <input id="date_entree" name="date_entree" type="date" class="field-control" value="{{ old('date_entree') }}" required>
                        @error('date_entree') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn-ink justify-content-center">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
```

In `resources/views/admin/coaches/edit.blade.php`, insert these two fields before the Contact field:

```blade
                    <div class="mb-3">
                        <label for="name" class="field-label">Nom complet</label>
                        <input id="name" name="name" type="text" class="field-control" value="{{ old('name', $coach->user->name) }}" required>
                        @error('name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="field-label">E-mail</label>
                        <input id="email" name="email" type="email" class="field-control" value="{{ old('email', $coach->user->email) }}" required>
                        <p class="field-hint">Le coach se connectera avec cette adresse.</p>
                        @error('email') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
```

- [ ] **Step 4: The "…" menu, the confirmation modal and the three tables**

Create `resources/views/components/menu-actions.blade.php`:

```blade
{{-- strategy "fixed" : sans elle, le menu serait coupé par l'overflow: hidden de .table-card. --}}
<div class="dropdown d-inline-block">
    <button type="button" class="btn-outline btn-sm menu-actions-declencheur"
            data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}'
            aria-expanded="false" aria-label="Actions">…</button>
    <ul class="dropdown-menu dropdown-menu-end menu-actions">
        {{ $slot }}
    </ul>
</div>
```

In `resources/views/layouts/app.blade.php`, add just before `</body>`:

```blade
    <div class="modal fade" id="modale-confirmation" tabindex="-1" aria-labelledby="modale-confirmation-titre" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modale-cafab">
                <div class="modal-body">
                    <h2 class="section-title mb-2" id="modale-confirmation-titre">Confirmation</h2>
                    <p class="mb-0" data-confirmation-message></p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn-outline btn-sm" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn-ink btn-sm" data-confirmation-valider>Confirmer</button>
                </div>
            </div>
        </div>
    </div>
```

In `resources/views/admin/coaches/index.blade.php`:

1. Right after `<x-onglets.registre />`, add:

```blade
    @if ($identifiants = session('identifiants'))
        <div class="callout-info mb-4">
            <div>
                <p class="fw-bold mb-1">Identifiants à transmettre à {{ $identifiants['nom'] }}</p>
                <p class="mb-1">Email : <span class="mono">{{ $identifiants['email'] }}</span> · Mot de passe provisoire : <span class="mono">{{ $identifiants['mot_de_passe'] }}</span></p>
                <p class="field-hint mb-0">Ce mot de passe ne sera plus affiché. Le coach devra le changer à sa première connexion.</p>
            </div>
            <button type="button" class="btn-outline btn-sm" data-copier="Email : {{ $identifiants['email'] }} · Mot de passe : {{ $identifiants['mot_de_passe'] }}">Copier</button>
        </div>
    @endif
```

2. Replace the content of the Actions `<td>` with:

```blade
                        <td class="text-end">
                            <x-menu-actions>
                                <li><a class="dropdown-item" href="{{ route('admin.coaches.edit', $coach) }}">Modifier</a></li>
                                <li>
                                    <form action="{{ route('admin.coaches.toggle-statut', $coach) }}" method="POST"
                                          data-confirmer="{{ $coach->statut->value === 'actif' ? 'Désactiver' : 'Réactiver' }} le compte de {{ $coach->user->name }} ?">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="dropdown-item">{{ $coach->statut->value === 'actif' ? 'Désactiver' : 'Réactiver' }}</button>
                                    </form>
                                </li>
                                <li>
                                    <form action="{{ route('admin.coaches.regenerate-pin', $coach) }}" method="POST"
                                          data-confirmer="Générer un nouveau code PIN pour {{ $coach->user->name }} ? L'ancien ne fonctionnera plus au kiosque.">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="dropdown-item">Régénérer le PIN</button>
                                    </form>
                                </li>
                                <li>
                                    <form action="{{ route('admin.coaches.reset-password', $coach) }}" method="POST"
                                          data-confirmer="Réinitialiser le mot de passe de {{ $coach->user->name }} ? Ses sessions seront fermées et il devra choisir un nouveau mot de passe.">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="dropdown-item">Réinitialiser le mot de passe</button>
                                    </form>
                                </li>
                            </x-menu-actions>
                        </td>
```

In `resources/views/registre/filles/index.blade.php`, replace the content of the Actions `<td>` with:

```blade
                        <td class="text-end">
                            <x-menu-actions>
                                <li><a class="dropdown-item" href="{{ route('filles.edit', $fille) }}">Modifier</a></li>
                                @can('toggleStatut', $fille)
                                    <li>
                                        <form action="{{ route('filles.toggle-statut', $fille) }}" method="POST"
                                              data-confirmer="{{ $fille->statut->value === 'actif' ? 'Désactiver' : 'Réactiver' }} la fiche de {{ $fille->prenom }} {{ $fille->nom }} ?">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="dropdown-item">{{ $fille->statut->value === 'actif' ? 'Désactiver' : 'Réactiver' }}</button>
                                        </form>
                                    </li>
                                @endcan
                                <li>
                                    <form action="{{ route('filles.regenerate-pin', $fille) }}" method="POST"
                                          data-confirmer="Générer un nouveau code PIN pour {{ $fille->prenom }} ? L'ancien ne fonctionnera plus au kiosque.">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="dropdown-item">Régénérer le PIN</button>
                                    </form>
                                </li>
                            </x-menu-actions>
                        </td>
```

In `resources/views/plannings/index.blade.php`, replace the `@can('update', $planning)` branch of the Actions `<td>` (added by Task 8) with:

```blade
                            @can('update', $planning)
                                <x-menu-actions>
                                    <li><a class="dropdown-item" href="{{ route('plannings.edit', $planning) }}">Modifier</a></li>
                                    <li>
                                        <form action="{{ route('plannings.toggle-actif', $planning) }}" method="POST"
                                              data-confirmer="{{ $planning->actif ? 'Désactiver' : 'Réactiver' }} le créneau du {{ $planning->jour_semaine->libelle() }} ?">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="dropdown-item">{{ $planning->actif ? 'Désactiver' : 'Réactiver' }}</button>
                                        </form>
                                    </li>
                                </x-menu-actions>
                            @else
                                <span class="field-hint">—</span>
                            @endcan
```

In the three tables, give the Actions `<th>` the class `text-end` to align with the "…" buttons.

- [ ] **Step 5: The three scripts and the styles**

Create `resources/js/confirmation.js`:

```js
// Toute <form data-confirmer="…"> passe par la fenêtre de confirmation avant d'être envoyée.
document.addEventListener('DOMContentLoaded', () => {
    const element = document.getElementById('modale-confirmation');

    if (!element) {
        return;
    }

    const modale = new window.bootstrap.Modal(element);
    const message = element.querySelector('[data-confirmation-message]');
    const valider = element.querySelector('[data-confirmation-valider]');
    let formulaireEnAttente = null;

    document.addEventListener('submit', (evenement) => {
        const formulaire = evenement.target;

        if (!(formulaire instanceof HTMLFormElement) || !formulaire.dataset.confirmer || formulaire.dataset.confirme === '1') {
            return;
        }

        evenement.preventDefault();
        formulaireEnAttente = formulaire;
        message.textContent = formulaire.dataset.confirmer;
        modale.show();
    });

    valider.addEventListener('click', () => {
        if (!formulaireEnAttente) {
            return;
        }

        formulaireEnAttente.dataset.confirme = '1';
        modale.hide();
        formulaireEnAttente.requestSubmit();
    });
});
```

Create `resources/js/copier.js`:

```js
document.addEventListener('click', async (evenement) => {
    const bouton = evenement.target.closest('[data-copier], [data-copier-champ]');

    if (!bouton) {
        return;
    }

    const texte = bouton.dataset.copierChamp
        ? document.getElementById(bouton.dataset.copierChamp)?.value
        : bouton.dataset.copier;

    if (!texte) {
        return;
    }

    try {
        await navigator.clipboard.writeText(texte);
        const libelle = bouton.textContent;
        bouton.textContent = 'Copié';
        setTimeout(() => {
            bouton.textContent = libelle;
        }, 1500);
    } catch {
        // Presse-papiers indisponible (page hors contexte sécurisé) : l'utilisateur copie à la main.
        window.prompt('Copiez le texte ci-dessous :', texte);
    }
});
```

Create `resources/js/mot-de-passe-provisoire.js` (same character sets as `App\Services\GenerateurMotDePasse`):

```js
const JEUX = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#$%&*?-_+='];

function aleatoire(max) {
    const tampon = new Uint32Array(1);
    const limite = Math.floor(0x100000000 / max) * max; // rejet des tirages biaisés

    do {
        crypto.getRandomValues(tampon);
    } while (tampon[0] >= limite);

    return tampon[0] % max;
}

export function genererMotDePasse(longueur = 10) {
    const tous = JEUX.join('');
    const caracteres = JEUX.map((jeu) => jeu[aleatoire(jeu.length)]);

    while (caracteres.length < longueur) {
        caracteres.push(tous[aleatoire(tous.length)]);
    }

    for (let i = caracteres.length - 1; i > 0; i -= 1) {
        const j = aleatoire(i + 1);
        [caracteres[i], caracteres[j]] = [caracteres[j], caracteres[i]];
    }

    return caracteres.join('');
}

document.addEventListener('click', (evenement) => {
    const bouton = evenement.target.closest('[data-generer-mot-de-passe]');

    if (!bouton) {
        return;
    }

    const champ = document.getElementById(bouton.dataset.genererMotDePasse);

    if (champ) {
        champ.value = genererMotDePasse();
    }
});
```

In `resources/js/app.js`, add after the existing imports:

```js
import './confirmation';
import './copier';
import './mot-de-passe-provisoire';
```

Append to `resources/css/_cafab-app.scss`:

```scss
/* Menu « … » des tableaux */
.menu-actions-declencheur {
  min-width: 40px;
  justify-content: center;
  font-weight: 800;
  letter-spacing: .1em;
}

.menu-actions {
  border-radius: var(--r-btn);
  padding: 6px;
  font-family: var(--font-body);
  font-size: 14px;
}

.menu-actions .dropdown-item {
  border-radius: var(--r-btn-sm);
  padding: 8px 12px;
  width: 100%;
  text-align: left;
}

/* Fenêtres modales (confirmation, formulaires de cachet) */
.modale-cafab {
  border: 1px solid var(--border);
  border-radius: var(--r-card);
  font-family: var(--font-body);
  color: var(--ink);
}
```

- [ ] **Step 6: Run the tests, build, full suite, commit**

Run: `php artisan test tests/Feature/Admin/CoachManagementTest.php tests/Feature/Registre` (expected: PASS), `npm run build`, `php artisan test` (all green), `vendor/bin/pint --dirty`.

```bash
git checkout -- package-lock.json 2>/dev/null; git add -A
git commit -m "$(cat <<'EOF'
feat(registre): provisional password for new coaches, full edit, row menus

Creating a coach now proposes a 10-character provisional password and
shows the credentials to hand over once; the admin can also reset a
coach's password. The edit form covers name and email too. Row actions
of the Coachs, Filles and Planning tables move into a "…" menu, and
state-changing actions ask for confirmation.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 10: Tableau de bord et détail d'une prestation (#4, #7)

**Files:**
- Modify: `resources/views/dashboard.blade.php`, `app/Http/Controllers/DashboardController.php`
- Modify: `app/Http/Controllers/Admin/PrestationController.php` (`show()`)
- Modify: `resources/views/components/badge-cachet.blade.php`
- Modify: `resources/views/admin/prestations/show.blade.php`
- Test: create `tests/Feature/DashboardTest.php`, create `tests/Feature/Admin/PrestationDetailTest.php`

**Interfaces:**
- Consumes: `<x-menu-actions>`, `.modale-cafab` (Task 9); `<x-onglets.prestations />` (Task 8).
- Produces: `PrestationController::show()` passes `$indicateurs = ['total_du' => float, 'valide' => float, 'reste' => float, 'a_traiter' => int]` (same definitions as the Paiements screen: cancelled cachets excluded, "validé" = `validee_payee`, "à traiter" = `declaree_payee` + `declaree_non_payee`). `<x-badge-cachet :statut="..." :detail="'14 sept.'" />` appends ` · 14 sept.` to the label.

- [ ] **Step 1: Write the tests**

Create `tests/Feature/DashboardTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;

it('keeps only the indicators on the admin dashboard', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Coachs actifs')
        ->assertDontSee('link-tile', false);
});
```

Create `tests/Feature/Admin/PrestationDetailTest.php`:

```php
<?php

use App\Enums\StatutCachet;
use App\Enums\UserRole;
use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Prestation;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->prestation = Prestation::factory()->create(['titre' => 'Nuit du Patrimoine']);
});

it('shows the four indicators of the prestation', function () {
    Cachet::factory()->create(['prestation_id' => $this->prestation->id, 'montant' => 15000, 'statut' => StatutCachet::ValideePayee]);
    Cachet::factory()->create(['prestation_id' => $this->prestation->id, 'montant' => 20000, 'statut' => StatutCachet::DeclareePayee]);
    Cachet::factory()->create(['prestation_id' => $this->prestation->id, 'montant' => 9000, 'statut' => StatutCachet::Annule]);

    $this->actingAs($this->admin)->get(route('admin.prestations.show', $this->prestation))
        ->assertOk()
        ->assertViewHas('indicateurs', ['total_du' => 35000.0, 'valide' => 15000.0, 'reste' => 20000.0, 'a_traiter' => 1])
        ->assertSee('35 000 F')
        ->assertSee('Détail par fille');
});

it('offers Valider and a menu opening the adjust and correct forms for an open cachet', function () {
    $cachet = Cachet::factory()->create([
        'prestation_id' => $this->prestation->id,
        'fille_id' => Fille::factory()->create(['prenom' => 'Sènami', 'nom' => 'Hounkpatin'])->id,
        'statut' => StatutCachet::DeclareePayee,
        'declaree_at' => Carbon::parse('2026-09-14 18:00:00'),
    ]);

    $this->actingAs($this->admin)->get(route('admin.prestations.show', $this->prestation))
        ->assertSee('Déclarée payée · 14 sept.')
        ->assertSee('Valider')
        ->assertSee('#modale-ajuster-'.$cachet->id, false)
        ->assertSee('#modale-corriger-'.$cachet->id, false);
});

it('shows no action on a validated cachet', function () {
    $cachet = Cachet::factory()->create(['prestation_id' => $this->prestation->id, 'statut' => StatutCachet::ValideePayee]);

    $this->actingAs($this->admin)->get(route('admin.prestations.show', $this->prestation))
        ->assertDontSee('modale-ajuster-'.$cachet->id, false);
});
```

Before running, open `database/factories/CachetFactory.php` and `PrestationFactory.php`: if a column used above (`montant`, `statut`, `declaree_at`, `titre`) has no default or a different name, adapt the test data, not the assertions.

Run: `php artisan test tests/Feature/DashboardTest.php tests/Feature/Admin/PrestationDetailTest.php`
Expected: FAIL (tiles still there, no `indicateurs`).

- [ ] **Step 2: Dashboard**

In `resources/views/dashboard.blade.php`, delete the whole second `<div class="row g-3">` of the admin branch (the three `link-tile` cards Pointages, Paiements and Rapports). In `app/Http/Controllers/DashboardController.php`, delete the now-unused `'seancesEnCours'` and `'cachetsAValider'` entries (and any import left unused).

- [ ] **Step 3: Controller and badge**

In `app/Http/Controllers/Admin/PrestationController.php`, add `use App\Enums\StatutCachet;` and `use App\Models\Cachet;` if missing, and replace `show()`:

```php
    public function show(Prestation $prestation): View
    {
        $prestation->load(['cachets.fille', 'cachets.valideParUser', 'cachets.corrigeParUser']);

        // Mêmes définitions que l'écran Paiements : les cachets annulés ne comptent pas.
        $cachets = $prestation->cachets->reject(fn (Cachet $cachet) => $cachet->statut === StatutCachet::Annule);
        $totalDu = (float) $cachets->sum(fn (Cachet $cachet) => (float) $cachet->montant);
        $valide = (float) $cachets->where('statut', StatutCachet::ValideePayee)->sum(fn (Cachet $cachet) => (float) $cachet->montant);

        $indicateurs = [
            'total_du' => $totalDu,
            'valide' => $valide,
            'reste' => $totalDu - $valide,
            'a_traiter' => $cachets->whereIn('statut', [StatutCachet::DeclareePayee, StatutCachet::DeclareeNonPayee])->count(),
        ];

        return view('admin.prestations.show', compact('prestation', 'indicateurs'));
    }
```

In `resources/views/components/badge-cachet.blade.php`, change the props to `@props(['statut', 'detail' => null])` and the label line to:

```blade
    {{ $config['label'] }}{{ $detail ? ' · '.$detail : '' }}
```

- [ ] **Step 4: The detail screen**

Replace the whole of `resources/views/admin/prestations/show.blade.php`:

```blade
@php
    $formaterMontant = fn ($montant) => number_format((float) $montant, 0, ',', ' ').' F';
    $cachetsOuverts = $prestation->cachets->reject->estFinalise();
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ $prestation->titre }}</h1>
            <p class="field-hint mb-0">
                {{ $prestation->lieu }} · {{ $prestation->date->translatedFormat('l j F Y') }} ·
                <span class="badge-st {{ $prestation->statut->value === 'active' ? 'st-heure' : 'st-absent' }}">
                    {{ $prestation->statut->value === 'active' ? 'Active' : 'Annulée' }}
                </span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.prestations.index') }}" class="btn-outline">Retour aux prestations</a>
        </div>
    </x-slot>

    <x-onglets.prestations />

    @if ($errors->any())
        <div class="callout-danger mb-4">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="kpi">
                <div class="label">Total dû</div>
                <div class="value">{{ $formaterMontant($indicateurs['total_du']) }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi">
                <div class="label">Validé payé</div>
                <div class="value ok">{{ $formaterMontant($indicateurs['valide']) }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi">
                <div class="label">Reste à payer</div>
                <div class="value warn">{{ $formaterMontant($indicateurs['reste']) }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi kpi-ink">
                <div class="label">Déclarations à traiter</div>
                <div class="value">{{ $indicateurs['a_traiter'] }}</div>
            </div>
        </div>
    </div>

    <p class="section-title mb-2">Détail par fille · cachet par défaut {{ $formaterMontant($prestation->montant_defaut) }}</p>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Fille</th>
                    <th class="text-end">Montant</th>
                    <th>Déclaration</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prestation->cachets as $cachet)
                    <tr @class(['pending' => in_array($cachet->statut->value, ['declaree_payee', 'declaree_non_payee'], true)])>
                        <td class="fw-bold">{{ $cachet->fille->prenom }} {{ $cachet->fille->nom }}</td>
                        <td class="text-end time">{{ $formaterMontant($cachet->montant) }}</td>
                        <td>
                            <x-badge-cachet :statut="$cachet->statut" :detail="$cachet->declaree_at?->translatedFormat('j M')" />
                        </td>
                        <td class="text-end">
                            @unless ($cachet->estFinalise())
                                <div class="d-inline-flex gap-2 align-items-center">
                                    <form action="{{ route('admin.cachets.valider', $cachet) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn-success">Valider</button>
                                    </form>
                                    <x-menu-actions>
                                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modale-ajuster-{{ $cachet->id }}">Ajuster le montant</button></li>
                                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modale-corriger-{{ $cachet->id }}">Corriger la déclaration</button></li>
                                    </x-menu-actions>
                                </div>
                            @endunless

                            @if ($cachet->statut->value === 'validee_payee')
                                @if ($cachet->depense_creee_at)
                                    <span class="field-hint text-amount-ok">Dépense créée dans Caisse CAFAB</span>
                                @else
                                    <div class="d-inline-flex gap-2 align-items-center">
                                        <span class="field-error mb-0">Dépense non créée : {{ $cachet->depense_erreur }}</span>
                                        <form action="{{ route('admin.cachets.reessayer-depense', $cachet) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn-ink btn-sm">Réessayer</button>
                                        </form>
                                    </div>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 field-hint">Aucun cachet pour cette prestation.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @foreach ($cachetsOuverts as $cachet)
        <div class="modal fade" id="modale-ajuster-{{ $cachet->id }}" tabindex="-1" aria-labelledby="titre-ajuster-{{ $cachet->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content modale-cafab" action="{{ route('admin.cachets.ajuster-montant', $cachet) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="modal-body">
                        <h2 class="section-title mb-3" id="titre-ajuster-{{ $cachet->id }}">Ajuster le montant · {{ $cachet->fille->prenom }} {{ $cachet->fille->nom }}</h2>
                        <label for="montant-{{ $cachet->id }}" class="field-label">Montant (F)</label>
                        <input id="montant-{{ $cachet->id }}" type="number" step="0.01" min="0" name="montant" value="{{ $cachet->montant }}" class="field-control" required>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn-outline btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn-ink btn-sm">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modale-corriger-{{ $cachet->id }}" tabindex="-1" aria-labelledby="titre-corriger-{{ $cachet->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content modale-cafab" action="{{ route('admin.cachets.corriger', $cachet) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="modal-body">
                        <h2 class="section-title mb-3" id="titre-corriger-{{ $cachet->id }}">Corriger la déclaration · {{ $cachet->fille->prenom }} {{ $cachet->fille->nom }}</h2>
                        <label for="statut-{{ $cachet->id }}" class="field-label">Nouvelle déclaration</label>
                        <select id="statut-{{ $cachet->id }}" name="statut" class="field-select mb-3">
                            <option value="declaree_payee">Déclarée payée</option>
                            <option value="declaree_non_payee">Déclarée non payée</option>
                        </select>
                        <label for="motif-{{ $cachet->id }}" class="field-label">Motif (obligatoire)</label>
                        <textarea id="motif-{{ $cachet->id }}" name="motif" class="field-control" rows="3" required minlength="5"></textarea>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn-outline btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn-corrige">Corriger</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
</x-app-layout>
```

Check the column name of the default amount in `app/Models/Prestation.php` (`montant_defaut`); if it differs, use the real name in the section title.

- [ ] **Step 5: Run the tests, build, full suite, commit**

Run: `php artisan test tests/Feature/DashboardTest.php tests/Feature/Admin` (expected: PASS), `npm run build`, `php artisan test` (all green), `vendor/bin/pint --dirty`.

```bash
git checkout -- package-lock.json 2>/dev/null; git add -A
git commit -m "$(cat <<'EOF'
feat(ui): prestation detail screen per the maquette, lighter dashboard

The prestation detail now shows its four indicators and a "détail par
fille" table where each open cachet has a Valider button and a menu
opening the adjust and correct forms in a modal, instead of three
forms stacked in one cell. The admin dashboard keeps only its
indicators.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

---

## Notes for the controller (after the final review)

- Browser check in the worktree before the PR (the worktree has its own `.env`; point `DB_DATABASE` to a throwaway SQLite file, never to the main checkout's database): connexion (œil, « Se souvenir de moi »), mot de passe oublié (email lu dans `storage/logs/laravel.log`), création de coach puis première connexion forcée, menu « … » (le menu ne doit pas être coupé par le tableau), fenêtre de confirmation, onglets Registre/Planning/Prestations côté admin et côté coach, bandeau et notification navigateur, horloge sur les quatre écrans, pavé du kiosque centré, détail d'une prestation et ses deux fenêtres.
- After the merge, the main checkout's `.env` needs `APP_FALLBACK_LOCALE=en` (otherwise a message without French translation shows its raw key), then `php artisan migrate` and `npm run build`. The existing accounts keep `must_change_password = false`.

