# Planning & Pointage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the répétition schedule (récurrent + extraordinaire), the kiosk/coach dual-source attendance pointage with the validated non-conflict rule, punctuality calculation, and the admin calendar/history views — the second increment of Présence & Paiements CAFAB, built on the Fondations plan's registre/roles/security foundation.

**Architecture:** Two new schedule tables (`plannings_repetition`, `seances`) feed a polymorphic `pointages` table (a pointage belongs to either a `Coach` or a `Fille`). Attendance is recorded through two entry points that share one service (`PointageService`) enforcing the same business rule everywhere: whichever pointage lands first — self-service at the kiosk or the coach's roll call — is final. The kiosk is a rate-limited, unauthenticated surface identified only by PIN, mirroring the registre's PIN lookup pattern.

**Tech Stack:** Laravel 12, PHP 8.3+, Blade, Alpine.js (already CSP-enabled via `'unsafe-eval'`), Pest, Laravel Pint — all established by the Fondations plan, now merged into `develop`.

**Spec:** `C:\wamp64\www\caisse_cafab\documentations\presence-paiement-cafab\` — `technum_specifications-fonctionnelles_presence-paiement-cafab_20260922.docx` §2 "Planning des répétitions" and §3 "Pointage de présence", `technum_regles-metier_presence-paiement-cafab_20260922.docx` §2 "Pointage de présence". Builds directly on `docs/superpowers/plans/2026-09-22-fondations.md` (merged: roles, `Coach`/`Fille` roster, `PinGenerator`, `SecurityHeaders`).

## Global Constraints

- PHP 8.3+, Laravel 12 (unchanged from Fondations).
- No inline `style="..."` in Blade views — the CSP from Fondations (`style-src 'self'`) is already enforced globally; do not reintroduce what that plan removed.
- The seuil de retard fort is **15 minutes** (validated, not a hypothesis — see règles métier §2.2) and is a fixed constant in code, the same way Fondations fixed the 24h/48h Caisse CAFAB windows: not an admin-configurable setting.
- **Non-conflict rule (binding):** the first pointage recorded for a person at a séance is final. A coach's roll call can only act on people who have not yet pointed themselves; it can never overwrite an existing pointage. This must be enforced in one shared service, not duplicated per entry point.
- The kiosk is unauthenticated by design (PIN-only identification) but must be rate-limited — a 4-digit PIN space (10,000 combinations) is brute-forceable without one.
- Désactivation, never delete: a `planning_repetition` slot that's no longer used is deactivated (`actif = false`), never deleted — `seances` rows keep referencing it historically.
- A pointage, once recorded, is immutable except through the explicit admin correction path (motif obligatoire) — no entry point silently overwrites one.

---

## Task 1: Multi-role middleware + planning récurrent

**Files:**
- Modify: `app/Http/Middleware/EnsureUserHasRole.php` (accept a comma-separated role list)
- Create: `app/Enums/JourSemaine.php`
- Create: `database/migrations/xxxx_xx_xx_xxxxxx_create_plannings_repetition_table.php`
- Create: `app/Models/PlanningRepetition.php`
- Create: `database/factories/PlanningRepetitionFactory.php`
- Create: `app/Http/Controllers/Admin/PlanningController.php`
- Create: `app/Http/Requests/Admin/StorePlanningRequest.php`
- Create: `app/Http/Requests/Admin/UpdatePlanningRequest.php`
- Modify: `routes/web.php` (add `admin.plannings.*` inside the existing `admin.` group)
- Create: `resources/views/admin/plannings/index.blade.php`
- Create: `resources/views/admin/plannings/create.blade.php`
- Create: `resources/views/admin/plannings/edit.blade.php`
- Test: `tests/Feature/Auth/MultiRoleMiddlewareTest.php`
- Test: `tests/Unit/Models/PlanningRepetitionTest.php`
- Test: `tests/Feature/Admin/PlanningManagementTest.php`

**Interfaces:**
- Produces: middleware syntax `role:admin,coach` (any listed role passes) in addition to the existing single-role `role:admin`/`role:coach` usage — fully backward compatible. `App\Enums\JourSemaine` (int-backed, `Lundi=1`..`Dimanche=7`, ISO weekday numbering). `PlanningRepetition` model (fields: `jour_semaine`, `heure_debut`, `coach_id`, `actif`), `PlanningRepetition::coach(): BelongsTo`.
- Consumes: `App\Models\Coach`, `App\Enums\UserRole` (Fondations).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Auth/MultiRoleMiddlewareTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['auth', 'role:admin,coach'])->get('/test/admin-or-coach', fn () => 'ok');
});

it('lets an admin through a multi-role gate', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/test/admin-or-coach')->assertOk();
});

it('lets a coach through the same multi-role gate', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get('/test/admin-or-coach')->assertOk();
});

it('still blocks a mismatched single role', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get('/admin/ping')->assertForbidden();
});
```

`tests/Unit/Models/PlanningRepetitionTest.php`:

```php
<?php

use App\Enums\JourSemaine;
use App\Models\PlanningRepetition;

it('casts jour_semaine to the JourSemaine enum', function () {
    $planning = PlanningRepetition::factory()->create(['jour_semaine' => 2]);

    expect($planning->jour_semaine)->toBe(JourSemaine::Mardi);
});

it('belongs to a referent coach', function () {
    $planning = PlanningRepetition::factory()->create();

    expect($planning->coach)->not->toBeNull();
});

it('defaults to actif', function () {
    $planning = PlanningRepetition::factory()->create();

    expect($planning->actif)->toBeTrue();
});
```

`tests/Feature/Admin/PlanningManagementTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\PlanningRepetition;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from managing plannings', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.plannings.index'))->assertForbidden();
});

it('lets the admin list plannings', function () {
    PlanningRepetition::factory()->count(2)->create();

    $this->actingAs($this->admin)->get(route('admin.plannings.index'))->assertOk();
});

it('lets the admin create a planning slot', function () {
    $coach = Coach::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('admin.plannings.store'), [
        'jour_semaine' => 2,
        'heure_debut' => '17:00',
        'coach_id' => $coach->id,
    ]);

    $response->assertRedirect(route('admin.plannings.index'));
    expect(PlanningRepetition::where('coach_id', $coach->id)->where('jour_semaine', 2)->exists())->toBeTrue();
});

it('lets the admin update a planning slot', function () {
    $planning = PlanningRepetition::factory()->create(['heure_debut' => '17:00']);
    $newCoach = Coach::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.plannings.update', $planning), [
            'jour_semaine' => $planning->jour_semaine->value,
            'heure_debut' => '18:30',
            'coach_id' => $newCoach->id,
        ])
        ->assertRedirect(route('admin.plannings.index'));

    expect($planning->fresh()->heure_debut)->toBe('18:30:00');
});

it('lets the admin deactivate then reactivate a planning slot', function () {
    $planning = PlanningRepetition::factory()->create(['actif' => true]);

    $this->actingAs($this->admin)->patch(route('admin.plannings.toggle-actif', $planning));
    expect($planning->fresh()->actif)->toBeFalse();

    $this->actingAs($this->admin)->patch(route('admin.plannings.toggle-actif', $planning));
    expect($planning->fresh()->actif)->toBeTrue();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/pest tests/Feature/Auth/MultiRoleMiddlewareTest.php tests/Unit/Models/PlanningRepetitionTest.php tests/Feature/Admin/PlanningManagementTest.php
```

Expected: FAIL — `role:admin,coach` syntax not supported yet, `PlanningRepetition` and the controller don't exist.

- [ ] **Step 3: Extend the role middleware to accept a comma-separated list**

`app/Http/Middleware/EnsureUserHasRole.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $allowed = explode(',', $roles);

        abort_if(! in_array($request->user()?->role?->value, $allowed, true), 403);

        return $next($request);
    }
}
```

- [ ] **Step 4: Create the `JourSemaine` enum**

`app/Enums/JourSemaine.php`:

```php
<?php

namespace App\Enums;

enum JourSemaine: int
{
    case Lundi = 1;
    case Mardi = 2;
    case Mercredi = 3;
    case Jeudi = 4;
    case Vendredi = 5;
    case Samedi = 6;
    case Dimanche = 7;

    public function libelle(): string
    {
        return match ($this) {
            self::Lundi => 'Lundi',
            self::Mardi => 'Mardi',
            self::Mercredi => 'Mercredi',
            self::Jeudi => 'Jeudi',
            self::Vendredi => 'Vendredi',
            self::Samedi => 'Samedi',
            self::Dimanche => 'Dimanche',
        };
    }
}
```

- [ ] **Step 5: Create the migration and model**

```bash
php artisan make:migration create_plannings_repetition_table
```

`database/migrations/xxxx_xx_xx_xxxxxx_create_plannings_repetition_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plannings_repetition', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('jour_semaine');
            $table->time('heure_debut');
            $table->foreignId('coach_id')->constrained()->restrictOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plannings_repetition');
    }
};
```

`app/Models/PlanningRepetition.php`:

```php
<?php

namespace App\Models;

use App\Enums\JourSemaine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanningRepetition extends Model
{
    use HasFactory;

    protected $table = 'plannings_repetition';

    protected $fillable = ['jour_semaine', 'heure_debut', 'coach_id', 'actif'];

    protected function casts(): array
    {
        return [
            'jour_semaine' => JourSemaine::class,
            'actif' => 'boolean',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class, 'planning_repetition_id');
    }
}
```

- [ ] **Step 6: Create the factory**

`database/factories/PlanningRepetitionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Coach;
use App\Models\PlanningRepetition;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanningRepetitionFactory extends Factory
{
    protected $model = PlanningRepetition::class;

    public function definition(): array
    {
        return [
            'jour_semaine' => $this->faker->numberBetween(1, 7),
            'heure_debut' => '17:00:00',
            'coach_id' => Coach::factory(),
            'actif' => true,
        ];
    }
}
```

- [ ] **Step 7: Create the form requests**

`app/Http/Requests/Admin/StorePlanningRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jour_semaine' => ['required', 'integer', 'between:1,7'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'coach_id' => ['required', 'exists:coaches,id'],
        ];
    }
}
```

`app/Http/Requests/Admin/UpdatePlanningRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jour_semaine' => ['required', 'integer', 'between:1,7'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'coach_id' => ['required', 'exists:coaches,id'],
        ];
    }
}
```

- [ ] **Step 8: Create the controller**

`app/Http/Controllers/Admin/PlanningController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanningRequest;
use App\Http\Requests\Admin\UpdatePlanningRequest;
use App\Models\Coach;
use App\Models\PlanningRepetition;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function index(): View
    {
        $plannings = PlanningRepetition::with('coach.user')->orderBy('jour_semaine')->orderBy('heure_debut')->get();

        return view('admin.plannings.index', compact('plannings'));
    }

    public function create(): View
    {
        $coaches = Coach::with('user')->where('statut', 'actif')->get();

        return view('admin.plannings.create', compact('coaches'));
    }

    public function store(StorePlanningRequest $request): RedirectResponse
    {
        PlanningRepetition::create($request->validated());

        return redirect()->route('admin.plannings.index')->with('message', 'Créneau ajouté.');
    }

    public function edit(PlanningRepetition $planning): View
    {
        $coaches = Coach::with('user')->where('statut', 'actif')->get();

        return view('admin.plannings.edit', compact('planning', 'coaches'));
    }

    public function update(UpdatePlanningRequest $request, PlanningRepetition $planning): RedirectResponse
    {
        $planning->update($request->validated());

        return redirect()->route('admin.plannings.index')->with('message', 'Créneau mis à jour.');
    }

    public function toggleActif(PlanningRepetition $planning): RedirectResponse
    {
        $planning->update(['actif' => ! $planning->actif]);

        return redirect()->route('admin.plannings.index')->with('message', 'Statut du créneau mis à jour.');
    }
}
```

- [ ] **Step 9: Register the routes**

In `routes/web.php`, inside the existing `admin.` group:

```php
use App\Http\Controllers\Admin\PlanningController;

Route::resource('plannings', PlanningController::class)
    ->except(['show', 'destroy'])
    ->parameters(['plannings' => 'planning']);
Route::patch('plannings/{planning}/toggle-actif', [PlanningController::class, 'toggleActif'])
    ->name('plannings.toggle-actif');
```

- [ ] **Step 10: Create the views**

`resources/views/admin/plannings/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Planning récurrent</h1>
    </x-slot>

    <a href="{{ route('admin.plannings.create') }}">Ajouter un créneau</a>

    <table>
        <thead>
            <tr>
                <th>Jour</th>
                <th>Heure</th>
                <th>Coach référent</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($plannings as $planning)
                <tr>
                    <td>{{ $planning->jour_semaine->libelle() }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($planning->heure_debut)->format('H:i') }}</td>
                    <td>{{ $planning->coach->user->name }}</td>
                    <td>{{ $planning->actif ? 'Actif' : 'Inactif' }}</td>
                    <td>
                        <a href="{{ route('admin.plannings.edit', $planning) }}">Modifier</a>
                        <form action="{{ route('admin.plannings.toggle-actif', $planning) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit">
                                {{ $planning->actif ? 'Désactiver' : 'Réactiver' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-app-layout>
```

`resources/views/admin/plannings/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Ajouter un créneau récurrent</h1>
    </x-slot>

    <form action="{{ route('admin.plannings.store') }}" method="POST">
        @csrf

        <label for="jour_semaine">Jour</label>
        <select id="jour_semaine" name="jour_semaine" required>
            @foreach (\App\Enums\JourSemaine::cases() as $jour)
                <option value="{{ $jour->value }}" {{ old('jour_semaine') == $jour->value ? 'selected' : '' }}>
                    {{ $jour->libelle() }}
                </option>
            @endforeach
        </select>
        @error('jour_semaine') <p>{{ $message }}</p> @enderror

        <label for="heure_debut">Heure de début</label>
        <input id="heure_debut" name="heure_debut" type="time" value="{{ old('heure_debut', '17:00') }}" required>
        @error('heure_debut') <p>{{ $message }}</p> @enderror

        <label for="coach_id">Coach référent</label>
        <select id="coach_id" name="coach_id" required>
            @foreach ($coaches as $coach)
                <option value="{{ $coach->id }}" {{ old('coach_id') == $coach->id ? 'selected' : '' }}>
                    {{ $coach->user->name }}
                </option>
            @endforeach
        </select>
        @error('coach_id') <p>{{ $message }}</p> @enderror

        <button type="submit">Créer</button>
    </form>
</x-app-layout>
```

`resources/views/admin/plannings/edit.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Modifier le créneau</h1>
    </x-slot>

    <form action="{{ route('admin.plannings.update', $planning) }}" method="POST">
        @csrf
        @method('PUT')

        <label for="jour_semaine">Jour</label>
        <select id="jour_semaine" name="jour_semaine" required>
            @foreach (\App\Enums\JourSemaine::cases() as $jour)
                <option value="{{ $jour->value }}"
                    {{ old('jour_semaine', $planning->jour_semaine->value) == $jour->value ? 'selected' : '' }}>
                    {{ $jour->libelle() }}
                </option>
            @endforeach
        </select>
        @error('jour_semaine') <p>{{ $message }}</p> @enderror

        <label for="heure_debut">Heure de début</label>
        <input id="heure_debut" name="heure_debut" type="time"
               value="{{ old('heure_debut', \Illuminate\Support\Carbon::parse($planning->heure_debut)->format('H:i')) }}" required>
        @error('heure_debut') <p>{{ $message }}</p> @enderror

        <label for="coach_id">Coach référent</label>
        <select id="coach_id" name="coach_id" required>
            @foreach ($coaches as $coach)
                <option value="{{ $coach->id }}" {{ old('coach_id', $planning->coach_id) == $coach->id ? 'selected' : '' }}>
                    {{ $coach->user->name }}
                </option>
            @endforeach
        </select>
        @error('coach_id') <p>{{ $message }}</p> @enderror

        <button type="submit">Enregistrer</button>
    </form>
</x-app-layout>
```

- [ ] **Step 11: Run the tests to verify they pass**

```bash
php artisan migrate
vendor/bin/pest tests/Feature/Auth/MultiRoleMiddlewareTest.php tests/Unit/Models/PlanningRepetitionTest.php tests/Feature/Admin/PlanningManagementTest.php
vendor/bin/pest
```

Expected: PASS, full suite green (the multi-role change is backward compatible — re-run the full suite to confirm nothing in Fondations' single-role usage broke).

- [ ] **Step 12: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add multi-role middleware and planning récurrent management"
```

---

## Task 2: Séances (schema, génération automatique, séance extraordinaire)

**Files:**
- Create: `app/Enums/TypeSeance.php`
- Create: `app/Enums/StatutSeance.php`
- Create: `database/migrations/xxxx_xx_xx_xxxxxx_create_seances_table.php`
- Create: `app/Models/Seance.php`
- Create: `database/factories/SeanceFactory.php`
- Create: `app/Services/SeanceGenerator.php`
- Create: `app/Console/Commands/GenererSeancesCommand.php`
- Create: `app/Console/Commands/DemarrerSeancesCommand.php`
- Modify: `routes/console.php` (schedule both commands — génération daily, démarrage every minute)
- Create: `app/Http/Controllers/SeanceController.php`
- Create: `app/Http/Requests/StoreSeanceExtraordinaireRequest.php`
- Modify: `routes/web.php` (add the shared `role:admin,coach` séance-extraordinaire routes)
- Create: `resources/views/seances/create-extraordinaire.blade.php`
- Test: `tests/Unit/Models/SeanceTest.php`
- Test: `tests/Unit/Services/SeanceGeneratorTest.php`
- Test: `tests/Unit/Console/DemarrerSeancesCommandTest.php`
- Test: `tests/Feature/SeanceExtraordinaireTest.php`

**Interfaces:**
- Consumes: `App\Models\PlanningRepetition`, `App\Models\Coach` (Task 1).
- Produces: `Seance` model (fields: `planning_repetition_id` nullable, `coach_id`, `date`, `heure_prevue`, `type`, `statut`, `cloturee_at`, `cloture_par_user_id`), `Seance::heurePrevueCarbon(): Carbon` (combines `date` + `heure_prevue` into one Carbon instant — later tasks need this for ponctualité math), `Seance::estEnCours(): bool`, `SeanceGenerator::genererPourLesProchainsJours(int $jours = 14): int` (returns count of séances created). The `seances:demarrer` command (scheduled every minute) is what actually makes a séance reach `en_cours` — Tasks 4 and 5 depend on this running, not just on the séance existing.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Models/SeanceTest.php`:

```php
<?php

use App\Enums\StatutSeance;
use App\Enums\TypeSeance;
use App\Models\Seance;

it('casts type and statut to their enums', function () {
    $seance = Seance::factory()->create(['type' => 'recurrente', 'statut' => 'a_venir']);

    expect($seance->type)->toBe(TypeSeance::Recurrente);
    expect($seance->statut)->toBe(StatutSeance::AVenir);
});

it('combines date and heure_prevue into one Carbon instant', function () {
    $seance = Seance::factory()->create(['date' => '2026-09-22', 'heure_prevue' => '17:00:00']);

    $instant = $seance->heurePrevueCarbon();

    expect($instant->format('Y-m-d H:i:s'))->toBe('2026-09-22 17:00:00');
});

it('reports en cours only when statut is en_cours', function () {
    $seance = Seance::factory()->create(['statut' => 'en_cours']);

    expect($seance->estEnCours())->toBeTrue();

    $seance->statut = StatutSeance::Cloturee;
    expect($seance->estEnCours())->toBeFalse();
});
```

`tests/Unit/Services/SeanceGeneratorTest.php`:

```php
<?php

use App\Enums\TypeSeance;
use App\Models\PlanningRepetition;
use App\Models\Seance;
use App\Services\SeanceGenerator;
use Illuminate\Support\Carbon;

it('generates a séance for each active planning occurring in the window', function () {
    Carbon::setTestNow('2026-09-22 08:00:00'); // a Tuesday

    $mardi = PlanningRepetition::factory()->create(['jour_semaine' => 2, 'heure_debut' => '17:00:00', 'actif' => true]);
    PlanningRepetition::factory()->create(['jour_semaine' => 2, 'heure_debut' => '17:00:00', 'actif' => false]);

    $created = (new SeanceGenerator())->genererPourLesProchainsJours(14);

    expect($created)->toBeGreaterThan(0);
    expect(Seance::where('planning_repetition_id', $mardi->id)->where('type', TypeSeance::Recurrente)->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('is idempotent — running it twice does not duplicate séances', function () {
    Carbon::setTestNow('2026-09-22 08:00:00');

    PlanningRepetition::factory()->create(['jour_semaine' => 2, 'heure_debut' => '17:00:00', 'actif' => true]);

    $generator = new SeanceGenerator();
    $first = $generator->genererPourLesProchainsJours(14);
    $second = $generator->genererPourLesProchainsJours(14);

    expect($second)->toBe(0);
    expect(Seance::count())->toBe($first);

    Carbon::setTestNow();
});
```

`tests/Feature/SeanceExtraordinaireTest.php`:

```php
<?php

use App\Enums\TypeSeance;
use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\Seance;
use App\Models\User;

it('lets an admin create a séance extraordinaire', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $coach = Coach::factory()->create();

    $response = $this->actingAs($admin)->post(route('seances.store-extraordinaire'), [
        'coach_id' => $coach->id,
        'date' => now()->addDay()->toDateString(),
        'heure_prevue' => '18:00',
    ]);

    $response->assertRedirect();
    expect(Seance::where('type', TypeSeance::Extraordinaire)->where('coach_id', $coach->id)->exists())->toBeTrue();
});

it('lets a coach create a séance extraordinaire', function () {
    $coach = Coach::factory()->create();
    $coachUser = $coach->user;

    $response = $this->actingAs($coachUser)->post(route('seances.store-extraordinaire'), [
        'coach_id' => $coach->id,
        'date' => now()->addDay()->toDateString(),
        'heure_prevue' => '18:00',
    ]);

    $response->assertRedirect();
});

it('blocks a fille-less guest from creating a séance extraordinaire', function () {
    $coach = Coach::factory()->create();

    $this->post(route('seances.store-extraordinaire'), [
        'coach_id' => $coach->id,
        'date' => now()->addDay()->toDateString(),
        'heure_prevue' => '18:00',
    ])->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/pest tests/Unit/Models/SeanceTest.php tests/Unit/Services/SeanceGeneratorTest.php tests/Feature/SeanceExtraordinaireTest.php
```

Expected: FAIL — `Seance`, `SeanceGenerator`, routes, and controller don't exist yet.

- [ ] **Step 3: Create the enums**

`app/Enums/TypeSeance.php`:

```php
<?php

namespace App\Enums;

enum TypeSeance: string
{
    case Recurrente = 'recurrente';
    case Extraordinaire = 'extraordinaire';
}
```

`app/Enums/StatutSeance.php`:

```php
<?php

namespace App\Enums;

enum StatutSeance: string
{
    case AVenir = 'a_venir';
    case EnCours = 'en_cours';
    case Cloturee = 'cloturee';
}
```

- [ ] **Step 4: Create the migration and model**

```bash
php artisan make:migration create_seances_table
```

`database/migrations/xxxx_xx_xx_xxxxxx_create_seances_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_repetition_id')->nullable()->constrained('plannings_repetition')->nullOnDelete();
            $table->foreignId('coach_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->time('heure_prevue');
            $table->string('type', 20);
            $table->string('statut', 20)->default('a_venir');
            $table->timestamp('cloturee_at')->nullable();
            $table->foreignId('cloture_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['planning_repetition_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seances');
    }
};
```

`app/Models/Seance.php`:

```php
<?php

namespace App\Models;

use App\Enums\StatutSeance;
use App\Enums\TypeSeance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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

    public function heurePrevueCarbon(): Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d').' '.$this->heure_prevue);
    }

    public function estEnCours(): bool
    {
        return $this->statut === StatutSeance::EnCours;
    }
}
```

- [ ] **Step 5: Create the factory**

`database/factories/SeanceFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Coach;
use App\Models\Seance;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeanceFactory extends Factory
{
    protected $model = Seance::class;

    public function definition(): array
    {
        return [
            'planning_repetition_id' => null,
            'coach_id' => Coach::factory(),
            'date' => now()->toDateString(),
            'heure_prevue' => '17:00:00',
            'type' => 'extraordinaire',
            'statut' => 'a_venir',
        ];
    }
}
```

- [ ] **Step 6: Create the `SeanceGenerator` service**

`app/Services/SeanceGenerator.php`:

```php
<?php

namespace App\Services;

use App\Enums\TypeSeance;
use App\Models\PlanningRepetition;
use App\Models\Seance;
use Illuminate\Support\Carbon;

class SeanceGenerator
{
    public function genererPourLesProchainsJours(int $jours = 14): int
    {
        $plannings = PlanningRepetition::where('actif', true)->get();
        $created = 0;

        foreach ($plannings as $planning) {
            for ($i = 0; $i < $jours; $i++) {
                $date = Carbon::now()->addDays($i);

                if ($date->isoWeekday() !== $planning->jour_semaine->value) {
                    continue;
                }

                $existe = Seance::where('planning_repetition_id', $planning->id)
                    ->whereDate('date', $date->toDateString())
                    ->exists();

                if ($existe) {
                    continue;
                }

                Seance::create([
                    'planning_repetition_id' => $planning->id,
                    'coach_id' => $planning->coach_id,
                    'date' => $date->toDateString(),
                    'heure_prevue' => $planning->heure_debut,
                    'type' => TypeSeance::Recurrente,
                    'statut' => 'a_venir',
                ]);
                $created++;
            }
        }

        return $created;
    }
}
```

- [ ] **Step 7: Create the artisan command and schedule it**

```bash
php artisan make:command GenererSeancesCommand
```

`app/Console/Commands/GenererSeancesCommand.php`:

```php
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
```

In `routes/console.php`, add:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seances:generer')->daily();
```

- [ ] **Step 8: Create the command that starts a séance automatically at its scheduled time**

`StatutSeance` has three states (`a_venir` → `en_cours` → `cloturee`), and every later task (kiosk pointage, coach roll call) only acts on a séance that is `en_cours`. The `cloturee` transition is a manual action (Task 5's "Clôturer" button), but nothing yet moves a séance from `a_venir` to `en_cours` when its `heure_prevue` arrives — without this, no séance this plan generates would ever become pointable. Add a second command for this half of the lifecycle:

```bash
php artisan make:command DemarrerSeancesCommand
```

`app/Console/Commands/DemarrerSeancesCommand.php`:

```php
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
```

Filtering in PHP after fetching the (small) set of `a_venir` séances avoids writing a date+time SQL comparison that behaves differently between SQLite (tests) and MySQL (production) — `heurePrevueCarbon()` already combines `date` and `heure_prevue` correctly in one place (Task 2, Step 4).

In `routes/console.php`, add alongside the daily generation:

```php
Schedule::command('seances:demarrer')->everyMinute();
```

Add the test:

`tests/Unit/Console/DemarrerSeancesCommandTest.php`:

```php
<?php

use App\Enums\StatutSeance;
use App\Models\Seance;
use Illuminate\Support\Carbon;

it('starts a séance once its heure_prevue has arrived', function () {
    Carbon::setTestNow('2026-09-22 17:00:30');

    $seance = Seance::factory()->create([
        'date' => '2026-09-22',
        'heure_prevue' => '17:00:00',
        'statut' => StatutSeance::AVenir,
    ]);

    $this->artisan('seances:demarrer');

    expect($seance->fresh()->statut)->toBe(StatutSeance::EnCours);

    Carbon::setTestNow();
});

it('leaves a séance a_venir if its heure_prevue has not arrived yet', function () {
    Carbon::setTestNow('2026-09-22 16:00:00');

    $seance = Seance::factory()->create([
        'date' => '2026-09-22',
        'heure_prevue' => '17:00:00',
        'statut' => StatutSeance::AVenir,
    ]);

    $this->artisan('seances:demarrer');

    expect($seance->fresh()->statut)->toBe(StatutSeance::AVenir);

    Carbon::setTestNow();
});
```

Run it:

```bash
vendor/bin/pest tests/Unit/Console/DemarrerSeancesCommandTest.php
```

Expected: FAIL first (command doesn't exist), then PASS once the command above is added — treat this as its own small RED/GREEN cycle within this step.

- [ ] **Step 9: Create the séance-extraordinaire request and controller**

`app/Http/Requests/StoreSeanceExtraordinaireRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeanceExtraordinaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'coach_id' => ['required', 'exists:coaches,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'heure_prevue' => ['required', 'date_format:H:i'],
        ];
    }
}
```

`app/Http/Controllers/SeanceController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\TypeSeance;
use App\Http\Requests\StoreSeanceExtraordinaireRequest;
use App\Models\Coach;
use App\Models\Seance;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SeanceController extends Controller
{
    public function create(): View
    {
        $coaches = Coach::with('user')->where('statut', 'actif')->get();

        return view('seances.create-extraordinaire', compact('coaches'));
    }

    public function store(StoreSeanceExtraordinaireRequest $request): RedirectResponse
    {
        Seance::create([
            'planning_repetition_id' => null,
            'coach_id' => $request->validated('coach_id'),
            'date' => $request->validated('date'),
            'heure_prevue' => $request->validated('heure_prevue'),
            'type' => TypeSeance::Extraordinaire,
            'statut' => 'a_venir',
        ]);

        return redirect()->route('dashboard')->with('message', 'Séance extraordinaire créée.');
    }
}
```

- [ ] **Step 10: Register the routes**

In `routes/web.php`, near the other route groups (outside the `admin.` group, since both roles need it):

```php
use App\Http\Controllers\SeanceController;

Route::middleware(['auth', 'role:admin,coach'])->group(function () {
    Route::get('seances/extraordinaire/creer', [SeanceController::class, 'create'])->name('seances.create-extraordinaire');
    Route::post('seances/extraordinaire', [SeanceController::class, 'store'])->name('seances.store-extraordinaire');
});
```

- [ ] **Step 11: Create the view**

`resources/views/seances/create-extraordinaire.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Créer une séance extraordinaire</h1>
    </x-slot>

    <form action="{{ route('seances.store-extraordinaire') }}" method="POST">
        @csrf

        <label for="coach_id">Coach référent</label>
        <select id="coach_id" name="coach_id" required>
            @foreach ($coaches as $coach)
                <option value="{{ $coach->id }}" {{ old('coach_id') == $coach->id ? 'selected' : '' }}>
                    {{ $coach->user->name }}
                </option>
            @endforeach
        </select>
        @error('coach_id') <p>{{ $message }}</p> @enderror

        <label for="date">Date</label>
        <input id="date" name="date" type="date" value="{{ old('date') }}" required>
        @error('date') <p>{{ $message }}</p> @enderror

        <label for="heure_prevue">Heure prévue</label>
        <input id="heure_prevue" name="heure_prevue" type="time" value="{{ old('heure_prevue', '17:00') }}" required>
        @error('heure_prevue') <p>{{ $message }}</p> @enderror

        <button type="submit">Créer</button>
    </form>
</x-app-layout>
```

- [ ] **Step 12: Run the tests to verify they pass**

```bash
php artisan migrate
vendor/bin/pest tests/Unit/Models/SeanceTest.php tests/Unit/Services/SeanceGeneratorTest.php tests/Unit/Console/DemarrerSeancesCommandTest.php tests/Feature/SeanceExtraordinaireTest.php
vendor/bin/pest
```

Expected: PASS, full suite green.

- [ ] **Step 13: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add séances (récurrentes générées + extraordinaires)"
```

---

## Task 3: Pointages — schema, model, and the ponctualité + non-conflict service

**Files:**
- Create: `app/Enums/StatutPonctualite.php`
- Create: `app/Enums/SourcePointage.php`
- Create: `database/migrations/xxxx_xx_xx_xxxxxx_create_pointages_table.php`
- Create: `app/Models/Pointage.php`
- Create: `database/factories/PointageFactory.php`
- Create: `app/Exceptions/PointageException.php`
- Create: `app/Services/PointageService.php`
- Test: `tests/Unit/Models/PointageTest.php`
- Test: `tests/Unit/Services/PointageServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\Seance` (Task 2), `App\Models\Coach`, `App\Models\Fille` (Fondations).
- Produces: `Pointage` model (polymorphic `pointable`), `PointageService::pointer(Seance $seance, Model $personne, Carbon $heure, SourcePointage $source, ?User $parUser = null): Pointage` — the ONE place the non-conflict rule and the 15-minute ponctualité threshold are implemented; every later task (kiosk, coach roll call) calls this, never reimplements the logic. `PointageService::SEUIL_RETARD_FORT_MINUTES = 15`. `PointageException` (thrown when the séance isn't `en_cours`, or the person already has a pointage for this séance).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Models/PointageTest.php`:

```php
<?php

use App\Enums\SourcePointage;
use App\Enums\StatutPonctualite;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;

it('casts statut_ponctualite and source to their enums', function () {
    $pointage = Pointage::factory()->create([
        'statut_ponctualite' => 'en_retard',
        'source' => 'auto',
    ]);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::EnRetard);
    expect($pointage->source)->toBe(SourcePointage::Auto);
});

it('resolves the polymorphic pointable to a fille', function () {
    $fille = Fille::factory()->create();
    $pointage = Pointage::factory()->create([
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
    ]);

    expect($pointage->pointable->is($fille))->toBeTrue();
});

it('belongs to a séance', function () {
    $seance = Seance::factory()->create();
    $pointage = Pointage::factory()->create(['seance_id' => $seance->id]);

    expect($pointage->seance->is($seance))->toBeTrue();
});

it('rejects a second pointage for the same person at the same séance', function () {
    $seance = Seance::factory()->create();
    $fille = Fille::factory()->create();

    Pointage::factory()->create([
        'seance_id' => $seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
    ]);

    Pointage::factory()->create([
        'seance_id' => $seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
    ]);
})->throws(\Illuminate\Database\QueryException::class);
```

`tests/Unit/Services/PointageServiceTest.php`:

```php
<?php

use App\Enums\SourcePointage;
use App\Enums\StatutPonctualite;
use App\Enums\StatutSeance;
use App\Exceptions\PointageException;
use App\Models\Fille;
use App\Models\Seance;
use App\Services\PointageService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seance = Seance::factory()->create([
        'date' => '2026-09-22',
        'heure_prevue' => '17:00:00',
        'statut' => StatutSeance::EnCours,
    ]);
    $this->fille = Fille::factory()->create();
    $this->service = new PointageService();
});

it('marks a pointage à l’heure when arriving before or at the scheduled time', function () {
    $pointage = $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 16:58:00'), SourcePointage::Auto);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::ALHeure);
    expect($pointage->minutes_retard)->toBe(0);
});

it('marks en retard between 1 and 15 minutes late', function () {
    $pointage = $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 17:10:00'), SourcePointage::Auto);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::EnRetard);
    expect($pointage->minutes_retard)->toBe(10);
});

it('marks retard fort beyond 15 minutes late', function () {
    $pointage = $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 17:20:00'), SourcePointage::Auto);

    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::RetardFort);
    expect($pointage->minutes_retard)->toBe(20);
});

it('rejects a pointage for a séance that is not en cours', function () {
    $this->seance->update(['statut' => StatutSeance::AVenir]);

    $this->service->pointer($this->seance, $this->fille, Carbon::now(), SourcePointage::Auto);
})->throws(PointageException::class);

it('rejects a second pointage for the same person — first one wins', function () {
    $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 16:58:00'), SourcePointage::Auto);

    $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 17:30:00'), SourcePointage::Coach);
})->throws(PointageException::class);

it('lets a coach record a pointage at a specific backdated time, not just now', function () {
    $pointage = $this->service->pointer($this->seance, $this->fille, Carbon::parse('2026-09-22 17:02:00'), SourcePointage::Coach);

    expect($pointage->source)->toBe(SourcePointage::Coach);
    expect($pointage->statut_ponctualite)->toBe(StatutPonctualite::EnRetard);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/pest tests/Unit/Models/PointageTest.php tests/Unit/Services/PointageServiceTest.php
```

Expected: FAIL — `Pointage`, `PointageService`, `PointageException` don't exist yet.

- [ ] **Step 3: Create the enums**

`app/Enums/StatutPonctualite.php`:

```php
<?php

namespace App\Enums;

enum StatutPonctualite: string
{
    case ALHeure = 'a_l_heure';
    case EnRetard = 'en_retard';
    case RetardFort = 'retard_fort';
    case Absent = 'absent';
}
```

`app/Enums/SourcePointage.php`:

```php
<?php

namespace App\Enums;

enum SourcePointage: string
{
    case Auto = 'auto';
    case Coach = 'coach';
}
```

- [ ] **Step 4: Create the migration and model**

```bash
php artisan make:migration create_pointages_table
```

`database/migrations/xxxx_xx_xx_xxxxxx_create_pointages_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pointages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained()->cascadeOnDelete();
            $table->string('pointable_type');
            $table->unsignedBigInteger('pointable_id');
            $table->dateTime('pointe_a')->nullable();
            $table->string('statut_ponctualite', 20);
            $table->integer('minutes_retard')->nullable();
            $table->string('source', 10);
            $table->foreignId('pointe_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('corrige_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motif_correction')->nullable();
            $table->timestamps();

            $table->unique(['seance_id', 'pointable_type', 'pointable_id']);
            $table->index(['pointable_type', 'pointable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pointages');
    }
};
```

`app/Models/Pointage.php`:

```php
<?php

namespace App\Models;

use App\Enums\SourcePointage;
use App\Enums\StatutPonctualite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Pointage extends Model
{
    use HasFactory;

    protected $fillable = [
        'seance_id', 'pointable_type', 'pointable_id', 'pointe_a',
        'statut_ponctualite', 'minutes_retard', 'source',
        'pointe_par_user_id', 'corrige_par_user_id', 'motif_correction',
    ];

    protected function casts(): array
    {
        return [
            'pointe_a' => 'datetime',
            'statut_ponctualite' => StatutPonctualite::class,
            'source' => SourcePointage::class,
        ];
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function pointable(): MorphTo
    {
        return $this->morphTo();
    }

    public function pointeParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pointe_par_user_id');
    }

    public function corrigeParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrige_par_user_id');
    }
}
```

- [ ] **Step 5: Create the factory**

`database/factories/PointageFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use Illuminate\Database\Eloquent\Factories\Factory;

class PointageFactory extends Factory
{
    protected $model = Pointage::class;

    public function definition(): array
    {
        return [
            'seance_id' => Seance::factory(),
            'pointable_type' => Fille::class,
            'pointable_id' => Fille::factory(),
            'pointe_a' => now(),
            'statut_ponctualite' => 'a_l_heure',
            'minutes_retard' => 0,
            'source' => 'auto',
        ];
    }
}
```

- [ ] **Step 6: Create the exception and the service**

`app/Exceptions/PointageException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class PointageException extends RuntimeException
{
    public static function seanceNonOuverte(): self
    {
        return new self("Cette séance n'est pas en cours : le pointage n'est pas possible.");
    }

    public static function dejaPointe(): self
    {
        return new self('Cette personne a déjà été pointée pour cette séance.');
    }
}
```

`app/Services/PointageService.php`:

```php
<?php

namespace App\Services;

use App\Enums\SourcePointage;
use App\Enums\StatutPonctualite;
use App\Enums\StatutSeance;
use App\Exceptions\PointageException;
use App\Models\Pointage;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PointageService
{
    public const SEUIL_RETARD_FORT_MINUTES = 15;

    public function pointer(
        Seance $seance,
        Model $personne,
        Carbon $heure,
        SourcePointage $source,
        ?User $parUser = null
    ): Pointage {
        if ($seance->statut !== StatutSeance::EnCours) {
            throw PointageException::seanceNonOuverte();
        }

        $dejaPointe = Pointage::where('seance_id', $seance->id)
            ->where('pointable_type', $personne::class)
            ->where('pointable_id', $personne->id)
            ->exists();

        if ($dejaPointe) {
            throw PointageException::dejaPointe();
        }

        [$statut, $minutesRetard] = $this->calculerPonctualite($seance->heurePrevueCarbon(), $heure);

        return Pointage::create([
            'seance_id' => $seance->id,
            'pointable_type' => $personne::class,
            'pointable_id' => $personne->id,
            'pointe_a' => $heure,
            'statut_ponctualite' => $statut,
            'minutes_retard' => $minutesRetard,
            'source' => $source,
            'pointe_par_user_id' => $parUser?->id,
        ]);
    }

    /**
     * @return array{0: StatutPonctualite, 1: int}
     */
    private function calculerPonctualite(Carbon $heurePrevue, Carbon $heureArrivee): array
    {
        $minutesEcart = (int) floor(($heureArrivee->getTimestamp() - $heurePrevue->getTimestamp()) / 60);

        if ($minutesEcart <= 0) {
            return [StatutPonctualite::ALHeure, 0];
        }

        if ($minutesEcart <= self::SEUIL_RETARD_FORT_MINUTES) {
            return [StatutPonctualite::EnRetard, $minutesEcart];
        }

        return [StatutPonctualite::RetardFort, $minutesEcart];
    }
}
```

- [ ] **Step 7: Run the tests to verify they pass**

```bash
php artisan migrate
vendor/bin/pest tests/Unit/Models/PointageTest.php tests/Unit/Services/PointageServiceTest.php
vendor/bin/pest
```

Expected: PASS, full suite green.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add pointages schema and PointageService (ponctualité + non-conflict rule)"
```

---

## Task 4: Mode kiosque (identification par PIN, auto-pointage)

**Files:**
- Create: `app/Services/KioskIdentifier.php`
- Create: `app/Http/Controllers/Kiosque/IdentificationController.php`
- Create: `app/Http/Controllers/Kiosque/PointageController.php`
- Modify: `routes/web.php` (add the rate-limited `kiosque.*` route group)
- Create: `resources/views/kiosque/accueil.blade.php`
- Create: `resources/views/kiosque/menu.blade.php`
- Create: `resources/views/kiosque/confirmation.blade.php`
- Test: `tests/Unit/Services/KioskIdentifierTest.php`
- Test: `tests/Feature/Kiosque/IdentificationTest.php`
- Test: `tests/Feature/Kiosque/PointageTest.php`

**Interfaces:**
- Consumes: `App\Services\PointageService` (Task 3), `App\Models\Coach`, `App\Models\Fille` (Fondations), `App\Models\Seance` (Task 2).
- Produces: `KioskIdentifier::identifier(string $pin): Coach|Fille|null`. Session key `kiosque.identifie` (array: `type`, `id`) — set by the identification step, read and cleared by the pointage step; never persisted beyond one request cycle plus the confirmation view.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Services/KioskIdentifierTest.php`:

```php
<?php

use App\Models\Coach;
use App\Models\Fille;
use App\Services\KioskIdentifier;

it('identifies a fille by her pin', function () {
    $fille = Fille::factory()->create(['pin' => '1234']);

    $personne = (new KioskIdentifier())->identifier('1234');

    expect($personne)->not->toBeNull();
    expect($personne->is($fille))->toBeTrue();
});

it('identifies a coach by his pin', function () {
    $coach = Coach::factory()->create(['pin' => '5678']);

    $personne = (new KioskIdentifier())->identifier('5678');

    expect($personne->is($coach))->toBeTrue();
});

it('returns null for an unknown pin', function () {
    expect((new KioskIdentifier())->identifier('0000'))->toBeNull();
});
```

`tests/Feature/Kiosque/IdentificationTest.php`:

```php
<?php

use App\Enums\StatutSeance;
use App\Models\Fille;
use App\Models\Seance;

it('shows the kiosk home without authentication', function () {
    $this->get(route('kiosque.home'))->assertOk();
});

it('identifies a fille by pin and stores it in the session', function () {
    $fille = Fille::factory()->create(['pin' => '1234']);
    Seance::factory()->create(['statut' => StatutSeance::EnCours]);

    $response = $this->post(route('kiosque.identifier'), ['pin' => '1234']);

    $response->assertRedirect(route('kiosque.menu'));
    $this->assertEquals(['type' => Fille::class, 'id' => $fille->id], session('kiosque.identifie'));
});

it('rejects an unknown pin with an error, no redirect to the menu', function () {
    $response = $this->post(route('kiosque.identifier'), ['pin' => '9999']);

    $response->assertRedirect(route('kiosque.home'));
    $response->assertSessionHasErrors('pin');
    expect(session('kiosque.identifie'))->toBeNull();
});

it('rate-limits repeated pin attempts', function () {
    for ($i = 0; $i < 21; $i++) {
        $response = $this->post(route('kiosque.identifier'), ['pin' => '0000']);
    }

    $response->assertStatus(429);
});
```

`tests/Feature/Kiosque/PointageTest.php`:

```php
<?php

use App\Enums\SourcePointage;
use App\Enums\StatutSeance;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;

it('lets an identified fille pointer her presence at the séance en cours', function () {
    $fille = Fille::factory()->create(['pin' => '1234']);
    $seance = Seance::factory()->create(['statut' => StatutSeance::EnCours]);

    $this->post(route('kiosque.identifier'), ['pin' => '1234']);

    $response = $this->post(route('kiosque.pointer'));

    $response->assertOk();
    $pointage = Pointage::where('pointable_type', Fille::class)->where('pointable_id', $fille->id)->first();
    expect($pointage)->not->toBeNull();
    expect($pointage->source)->toBe(SourcePointage::Auto);
});

it('refuses to pointer without an identified session', function () {
    Seance::factory()->create(['statut' => StatutSeance::EnCours]);

    $this->post(route('kiosque.pointer'))->assertRedirect(route('kiosque.home'));
});

it('shows a friendly message rather than a 500 when no séance is en cours', function () {
    $fille = Fille::factory()->create(['pin' => '1234']);
    $this->post(route('kiosque.identifier'), ['pin' => '1234']);

    $response = $this->post(route('kiosque.pointer'));

    $response->assertOk();
    $response->assertSee('Aucune répétition en cours', false);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/pest tests/Unit/Services/KioskIdentifierTest.php tests/Feature/Kiosque/IdentificationTest.php tests/Feature/Kiosque/PointageTest.php
```

Expected: FAIL — routes, controllers, and `KioskIdentifier` don't exist yet.

- [ ] **Step 3: Create the `KioskIdentifier` service**

`app/Services/KioskIdentifier.php`:

```php
<?php

namespace App\Services;

use App\Models\Coach;
use App\Models\Fille;
use Illuminate\Database\Eloquent\Model;

class KioskIdentifier
{
    public function identifier(string $pin): ?Model
    {
        return Fille::where('pin', $pin)->first()
            ?? Coach::where('pin', $pin)->first();
    }
}
```

- [ ] **Step 4: Create the identification controller**

`app/Http/Controllers/Kiosque/IdentificationController.php`:

```php
<?php

namespace App\Http\Controllers\Kiosque;

use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\Fille;
use App\Models\Seance;
use App\Services\KioskIdentifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IdentificationController extends Controller
{
    public function home(): View
    {
        $seance = Seance::where('statut', 'en_cours')->latest('heure_prevue')->first();

        return view('kiosque.accueil', compact('seance'));
    }

    public function identifier(Request $request, KioskIdentifier $identifier): RedirectResponse
    {
        $request->validate(['pin' => ['required', 'string', 'size:4']]);

        $personne = $identifier->identifier($request->string('pin')->value());

        if (! $personne) {
            return redirect()->route('kiosque.home')->withErrors(['pin' => 'Code inconnu.']);
        }

        $request->session()->put('kiosque.identifie', [
            'type' => $personne::class,
            'id' => $personne->id,
        ]);

        $nomComplet = $personne instanceof Fille
            ? "{$personne->prenom} {$personne->nom}"
            : $personne->user->name;

        $request->session()->put('kiosque.nom', $nomComplet);

        return redirect()->route('kiosque.menu');
    }

    public function menu(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('kiosque.identifie')) {
            return redirect()->route('kiosque.home');
        }

        return view('kiosque.menu', ['nom' => $request->session()->get('kiosque.nom')]);
    }
}
```

- [ ] **Step 5: Create the kiosk pointage controller**

`app/Http/Controllers/Kiosque/PointageController.php`:

```php
<?php

namespace App\Http\Controllers\Kiosque;

use App\Enums\SourcePointage;
use App\Exceptions\PointageException;
use App\Http\Controllers\Controller;
use App\Models\Seance;
use App\Services\PointageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PointageController extends Controller
{
    public function store(Request $request, PointageService $pointageService): View|RedirectResponse
    {
        $identifie = $request->session()->get('kiosque.identifie');

        if (! $identifie) {
            return redirect()->route('kiosque.home');
        }

        $personne = $identifie['type']::find($identifie['id']);
        $seance = Seance::where('statut', 'en_cours')->latest('heure_prevue')->first();

        if (! $seance) {
            return view('kiosque.confirmation', [
                'nom' => $request->session()->get('kiosque.nom'),
                'erreur' => 'Aucune répétition en cours pour le moment.',
            ]);
        }

        try {
            $pointage = $pointageService->pointer($seance, $personne, Carbon::now(), SourcePointage::Auto);
        } catch (PointageException $e) {
            return view('kiosque.confirmation', [
                'nom' => $request->session()->get('kiosque.nom'),
                'erreur' => $e->getMessage(),
            ]);
        }

        $request->session()->forget(['kiosque.identifie', 'kiosque.nom']);

        return view('kiosque.confirmation', [
            'nom' => $identifie['type'] === \App\Models\Fille::class ? "{$personne->prenom}" : $personne->user->name,
            'pointage' => $pointage,
        ]);
    }
}
```

- [ ] **Step 6: Register the routes, rate-limiting only the PIN check**

The throttle exists to slow down PIN guessing, not to cap normal kiosk traffic — the kiosk reloads `home` after every pointage (Step 7's confirmation view redirects back after a few seconds), so a busy répétition with fifteen filles pointing in the same minute is legitimate, ordinary load on `home`/`menu`/`pointer`. Only `identifier` (the route that accepts a guessed PIN) gets throttled.

In `routes/web.php`:

```php
use App\Http\Controllers\Kiosque\IdentificationController;
use App\Http\Controllers\Kiosque\PointageController as KiosquePointageController;

Route::prefix('kiosque')->name('kiosque.')->group(function () {
    Route::get('/', [IdentificationController::class, 'home'])->name('home');
    Route::post('identifier', [IdentificationController::class, 'identifier'])
        ->middleware('throttle:20,1')
        ->name('identifier');
    Route::get('menu', [IdentificationController::class, 'menu'])->name('menu');
    Route::post('pointer', [KiosquePointageController::class, 'store'])->name('pointer');
});
```

- [ ] **Step 7: Create the views**

`resources/views/kiosque/accueil.blade.php`:

```blade
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pointage — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-6">
        <h1 class="text-2xl font-bold mb-2">Tape ton code</h1>
        @if ($seance)
            <p class="mb-4">Répétition en cours — début prévu {{ \Illuminate\Support\Carbon::parse($seance->heure_prevue)->format('H:i') }}</p>
        @else
            <p class="mb-4">Aucune répétition en cours pour le moment.</p>
        @endif

        @error('pin')
            <p class="text-red-600 mb-4">{{ $message }}</p>
        @enderror

        <form action="{{ route('kiosque.identifier') }}" method="POST" x-data="{ pin: '' }">
            @csrf
            <input type="hidden" name="pin" x-model="pin">
            <div class="text-3xl text-center tracking-widest mb-4" x-text="pin.padEnd(4, '_')"></div>
            <div class="grid grid-cols-3 gap-2">
                @foreach ([1,2,3,4,5,6,7,8,9] as $chiffre)
                    <button type="button" class="p-4 text-xl border rounded"
                            x-on:click="if (pin.length < 4) pin += '{{ $chiffre }}'">{{ $chiffre }}</button>
                @endforeach
                <button type="button" class="p-4 border rounded" x-on:click="pin = ''">Effacer</button>
                <button type="button" class="p-4 text-xl border rounded"
                        x-on:click="if (pin.length < 4) pin += '0'">0</button>
                <button type="submit" class="p-4 border rounded bg-black text-white" x-bind:disabled="pin.length !== 4">Valider</button>
            </div>
        </form>
    </div>
</body>
</html>
```

`resources/views/kiosque/menu.blade.php`:

```blade
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pointage — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-900 text-white flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-6 text-center">
        <p class="text-lg mb-1">Bonsoir</p>
        <h1 class="text-3xl font-bold mb-8">{{ $nom }}</h1>

        <form action="{{ route('kiosque.pointer') }}" method="POST">
            @csrf
            <button type="submit" class="w-full p-6 rounded bg-orange-600 text-lg font-bold">
                Pointer ma présence
            </button>
        </form>

        <a href="{{ route('kiosque.home') }}" class="block mt-6 text-sm text-gray-400">
            Ce n'est pas toi ? Touche ici pour revenir en arrière.
        </a>
    </div>
</body>
</html>
```

`resources/views/kiosque/confirmation.blade.php`:

```blade
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pointage — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen"
      x-data="{}" x-init="setTimeout(() => window.location = '{{ route('kiosque.home') }}', 4000)">
    <div class="w-full max-w-md p-6 text-center">
        @if (isset($erreur))
            <h1 class="text-2xl font-bold mb-4">{{ $nom }}, un instant.</h1>
            <p class="mb-4">{{ $erreur }}</p>
        @else
            <h1 class="text-2xl font-bold mb-4">C'est noté, {{ $nom }}.</h1>
            <p>Arrivée enregistrée à {{ $pointage->pointe_a->format('H:i') }}.</p>
        @endif
        <p class="mt-8 text-sm text-gray-500">Retour à l'accueil dans quelques secondes…</p>
    </div>
</body>
</html>
```

- [ ] **Step 8: Run the tests to verify they pass**

```bash
vendor/bin/pest tests/Unit/Services/KioskIdentifierTest.php tests/Feature/Kiosque/IdentificationTest.php tests/Feature/Kiosque/PointageTest.php
vendor/bin/pest
```

Expected: PASS, full suite green.

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add kiosk PIN identification and self-pointage"
```

---

## Task 5: Pointage par le coach (appel de groupe)

**Files:**
- Create: `app/Http/Controllers/Coach/PointageController.php`
- Create: `app/Http/Requests/Coach/MarquerPresenteRequest.php`
- Modify: `routes/web.php` (add the `coach.` prefixed group, `role:coach`)
- Create: `resources/views/coach/seance.blade.php`
- Test: `tests/Feature/Coach/PointageGroupeTest.php`

**Interfaces:**
- Consumes: `App\Services\PointageService` (Task 3), `App\Models\Seance`, `App\Models\Fille`, `App\Models\Coach` (Fondations/Task 2).
- Produces: routes `coach.seance` (GET, current/next séance for the logged-in coach), `coach.seance.marquer-presente` (PATCH), `coach.seance.cloturer` (PATCH).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Coach/PointageGroupeTest.php`:

```php
<?php

use App\Enums\SourcePointage;
use App\Enums\StatutSeance;
use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use App\Models\User;

beforeEach(function () {
    $this->coach = Coach::factory()->create();
    $this->coachUser = $this->coach->user;
    $this->seance = Seance::factory()->create(['coach_id' => $this->coach->id, 'statut' => StatutSeance::EnCours]);
});

it('blocks an admin from the coach roll-call screen', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('coach.seance'))->assertForbidden();
});

it('shows the current séance to its referent coach', function () {
    $this->actingAs($this->coachUser)->get(route('coach.seance'))->assertOk();
});

it('lets the coach mark a fille present who has not yet self-pointed', function () {
    $fille = Fille::factory()->create();

    $response = $this->actingAs($this->coachUser)->patch(route('coach.seance.marquer-presente'), [
        'seance_id' => $this->seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
    ]);

    $response->assertRedirect();
    $pointage = Pointage::where('pointable_id', $fille->id)->first();
    expect($pointage->source)->toBe(SourcePointage::Coach);
    expect($pointage->pointe_par_user_id)->toBe($this->coachUser->id);
});

it('lets the coach backdate a pointage to a specific time rather than now', function () {
    $fille = Fille::factory()->create();

    $this->actingAs($this->coachUser)->patch(route('coach.seance.marquer-presente'), [
        'seance_id' => $this->seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
        'heure' => '17:02',
    ]);

    $pointage = Pointage::where('pointable_id', $fille->id)->first();
    expect($pointage->pointe_a->format('H:i'))->toBe('17:02');
});

it('refuses to let the coach overwrite a pointage the fille already made herself', function () {
    $fille = Fille::factory()->create();
    Pointage::factory()->create([
        'seance_id' => $this->seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
        'source' => 'auto',
    ]);

    $response = $this->actingAs($this->coachUser)->patch(route('coach.seance.marquer-presente'), [
        'seance_id' => $this->seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
    ]);

    $response->assertSessionHasErrors();
    expect(Pointage::where('pointable_id', $fille->id)->count())->toBe(1);
});

it('lets the coach clôturer the séance', function () {
    $this->actingAs($this->coachUser)->patch(route('coach.seance.cloturer'), ['seance_id' => $this->seance->id]);

    expect($this->seance->fresh()->statut)->toBe(StatutSeance::Cloturee);
    expect($this->seance->fresh()->cloture_par_user_id)->toBe($this->coachUser->id);
});

it('blocks a coach from marking presence on a séance that is not theirs', function () {
    $autreCoach = Coach::factory()->create();
    $fille = Fille::factory()->create();

    $this->actingAs($autreCoach->user)->patch(route('coach.seance.marquer-presente'), [
        'seance_id' => $this->seance->id,
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
    ])->assertForbidden();
});

it('blocks a coach from clôturing a séance that is not theirs', function () {
    $autreCoach = Coach::factory()->create();

    $this->actingAs($autreCoach->user)->patch(route('coach.seance.cloturer'), [
        'seance_id' => $this->seance->id,
    ])->assertForbidden();

    expect($this->seance->fresh()->statut)->toBe(StatutSeance::EnCours);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/pest tests/Feature/Coach/PointageGroupeTest.php
```

Expected: FAIL — routes and controller don't exist yet.

- [ ] **Step 3: Create the form request**

`app/Http/Requests/Coach/MarquerPresenteRequest.php`:

```php
<?php

namespace App\Http\Requests\Coach;

use App\Models\Coach;
use App\Models\Fille;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarquerPresenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seance_id' => ['required', 'exists:seances,id'],
            'pointable_type' => ['required', Rule::in([Coach::class, Fille::class])],
            'pointable_id' => ['required', 'integer'],
            'heure' => ['nullable', 'date_format:H:i'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/Coach/PointageController.php`:

```php
<?php

namespace App\Http\Controllers\Coach;

use App\Enums\SourcePointage;
use App\Enums\StatutSeance;
use App\Exceptions\PointageException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coach\MarquerPresenteRequest;
use App\Models\Fille;
use App\Models\Seance;
use App\Services\PointageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PointageController extends Controller
{
    public function show(Request $request): View
    {
        $coach = $request->user()->coach;

        $seance = Seance::where('coach_id', $coach?->id)
            ->whereIn('statut', ['en_cours', 'a_venir'])
            ->orderBy('date')
            ->orderBy('heure_prevue')
            ->first();

        $pointages = $seance ? $seance->pointages()->with('pointable')->get() : collect();
        $filles = Fille::where('statut', 'actif')->orderBy('nom')->get();

        return view('coach.seance', compact('seance', 'pointages', 'filles'));
    }

    public function marquerPresente(MarquerPresenteRequest $request, PointageService $pointageService): RedirectResponse
    {
        $seance = Seance::findOrFail($request->validated('seance_id'));
        abort_if($seance->coach_id !== $request->user()->coach?->id, 403);

        $personne = $request->validated('pointable_type')::findOrFail($request->validated('pointable_id'));

        $heure = $request->filled('heure')
            ? Carbon::parse($seance->date->format('Y-m-d').' '.$request->validated('heure'))
            : Carbon::now();

        try {
            $pointageService->pointer($seance, $personne, $heure, SourcePointage::Coach, $request->user());
        } catch (PointageException $e) {
            return back()->withErrors(['pointage' => $e->getMessage()]);
        }

        return back()->with('message', 'Présence enregistrée.');
    }

    public function cloturer(Request $request): RedirectResponse
    {
        $seance = Seance::findOrFail($request->input('seance_id'));
        abort_if($seance->coach_id !== $request->user()->coach?->id, 403);

        $seance->update([
            'statut' => StatutSeance::Cloturee,
            'cloturee_at' => now(),
            'cloture_par_user_id' => $request->user()->id,
        ]);

        return redirect()->route('coach.seance')->with('message', 'Séance clôturée.');
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`:

```php
use App\Http\Controllers\Coach\PointageController as CoachPointageController;

Route::middleware(['auth', 'role:coach'])->prefix('coach')->name('coach.')->group(function () {
    Route::get('ma-seance', [CoachPointageController::class, 'show'])->name('seance');
    Route::patch('ma-seance/marquer-presente', [CoachPointageController::class, 'marquerPresente'])->name('seance.marquer-presente');
    Route::patch('ma-seance/cloturer', [CoachPointageController::class, 'cloturer'])->name('seance.cloturer');
});
```

- [ ] **Step 6: Create the view**

`resources/views/coach/seance.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Ma répétition</h1>
    </x-slot>

    @if (! $seance)
        <p>Aucune séance à venir ou en cours ne vous est actuellement assignée.</p>
    @else
        <p>
            {{ $seance->date->format('d/m/Y') }} — début prévu {{ \Illuminate\Support\Carbon::parse($seance->heure_prevue)->format('H:i') }}
            — statut : {{ $seance->statut->value }}
        </p>

        @if ($seance->estEnCours())
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Arrivée</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filles as $fille)
                        @php
                            $pointage = $pointages->first(fn ($p) => $p->pointable_type === \App\Models\Fille::class && $p->pointable_id === $fille->id);
                        @endphp
                        <tr>
                            <td>{{ $fille->prenom }} {{ $fille->nom }}</td>
                            <td>{{ $pointage?->pointe_a?->format('H:i') ?? '—' }}</td>
                            <td>{{ $pointage?->statut_ponctualite?->value ?? 'Pas encore pointée' }}</td>
                            <td>
                                @unless ($pointage)
                                    <form action="{{ route('coach.seance.marquer-presente') }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="seance_id" value="{{ $seance->id }}">
                                        <input type="hidden" name="pointable_type" value="{{ \App\Models\Fille::class }}">
                                        <input type="hidden" name="pointable_id" value="{{ $fille->id }}">
                                        <input type="time" name="heure">
                                        <button type="submit">Marquer présente</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <form action="{{ route('coach.seance.cloturer') }}" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="seance_id" value="{{ $seance->id }}">
                <button type="submit">Clôturer la séance</button>
            </form>
        @endif
    @endif
</x-app-layout>
```

- [ ] **Step 7: Run the tests to verify they pass**

```bash
vendor/bin/pest tests/Feature/Coach/PointageGroupeTest.php
vendor/bin/pest
```

Expected: PASS, full suite green.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add coach roll-call screen (mark present, backdate, clôturer)"
```

---

## Task 6: Vue calendrier admin

**Files:**
- Create: `app/Http/Controllers/Admin/CalendrierController.php`
- Modify: `routes/web.php` (add `admin.calendrier` inside the existing `admin.` group)
- Create: `resources/views/admin/calendrier/index.blade.php`
- Test: `tests/Feature/Admin/CalendrierTest.php`

**Interfaces:**
- Consumes: `App\Models\Seance`, `App\Models\Pointage` (Tasks 2 & 3).
- Produces: route `admin.calendrier` (GET, `?mois=YYYY-MM` optional query param, defaults to current month).

**Scope note:** the validated mockup shows prestations on this same calendar alongside séances. Prestations don't exist yet — that's the next plan's job. This task's calendar shows séances de répétition only; the next plan extends this same view to overlay prestations once that data exists, rather than this task inventing a placeholder for it now.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/CalendrierTest.php`:

```php
<?php

use App\Enums\StatutSeance;
use App\Enums\UserRole;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use App\Models\User;

it('blocks a coach from the admin calendar', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.calendrier'))->assertForbidden();
});

it('shows the admin the current month by default', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('admin.calendrier'))->assertOk();
});

it('computes the taux de présence for a clôturée séance', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $seance = Seance::factory()->create(['statut' => StatutSeance::Cloturee, 'date' => now()->toDateString()]);
    $filles = Fille::factory()->count(4)->create();

    foreach ($filles as $i => $fille) {
        Pointage::factory()->create([
            'seance_id' => $seance->id,
            'pointable_type' => Fille::class,
            'pointable_id' => $fille->id,
            'statut_ponctualite' => $i === 0 ? 'absent' : 'a_l_heure',
        ]);
    }

    $response = $this->actingAs($admin)->get(route('admin.calendrier', ['mois' => now()->format('Y-m')]));

    $response->assertOk();
    $response->assertSee('75');
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/pest tests/Feature/Admin/CalendrierTest.php
```

Expected: FAIL — route and controller don't exist yet.

- [ ] **Step 3: Create the controller**

`app/Http/Controllers/Admin/CalendrierController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutPonctualite;
use App\Enums\StatutSeance;
use App\Http\Controllers\Controller;
use App\Models\Seance;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CalendrierController extends Controller
{
    public function index(): View
    {
        $mois = request()->filled('mois')
            ? Carbon::createFromFormat('Y-m', request('mois'))
            : Carbon::now();

        $debut = $mois->copy()->startOfMonth();
        $fin = $mois->copy()->endOfMonth();

        $seances = Seance::whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->with('pointages')
            ->orderBy('date')
            ->get()
            ->map(function (Seance $seance) {
                $seance->taux_presence = $this->tauxPresence($seance);

                return $seance;
            });

        return view('admin.calendrier.index', [
            'mois' => $mois,
            'seances' => $seances,
        ]);
    }

    private function tauxPresence(Seance $seance): ?int
    {
        if ($seance->statut !== StatutSeance::Cloturee || $seance->pointages->isEmpty()) {
            return null;
        }

        $presentes = $seance->pointages->filter(
            fn ($p) => $p->statut_ponctualite !== StatutPonctualite::Absent
        )->count();

        return (int) round(($presentes / $seance->pointages->count()) * 100);
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, inside the existing `admin.` group:

```php
use App\Http\Controllers\Admin\CalendrierController;

Route::get('calendrier', [CalendrierController::class, 'index'])->name('calendrier');
```

- [ ] **Step 5: Create the view**

`resources/views/admin/calendrier/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Calendrier des répétitions — {{ $mois->translatedFormat('F Y') }}</h1>
    </x-slot>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Heure prévue</th>
                <th>Type</th>
                <th>Statut</th>
                <th>Taux de présence</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($seances as $seance)
                <tr>
                    <td>{{ $seance->date->format('d/m/Y') }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($seance->heure_prevue)->format('H:i') }}</td>
                    <td>{{ $seance->type->value }}</td>
                    <td>{{ $seance->statut->value }}</td>
                    <td>{{ $seance->taux_presence !== null ? $seance->taux_presence.' %' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Aucune séance ce mois-ci.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-app-layout>
```

- [ ] **Step 6: Run the test to verify it passes**

```bash
vendor/bin/pest tests/Feature/Admin/CalendrierTest.php
vendor/bin/pest
```

Expected: PASS, full suite green.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add admin calendar view with taux de présence"
```

---

## Task 7: Historique de ponctualité et correction d'un pointage

**Files:**
- Create: `app/Http/Controllers/Admin/PointageController.php`
- Create: `app/Http/Requests/Admin/CorrigerPointageRequest.php`
- Modify: `routes/web.php` (add `admin.pointages.*` inside the `admin.` group, and `coach.historique` inside the `coach.` group)
- Modify: `app/Http/Controllers/Coach/PointageController.php` (add an `historique` action)
- Create: `resources/views/admin/pointages/index.blade.php`
- Create: `resources/views/coach/historique.blade.php`
- Test: `tests/Feature/Admin/PointageHistoriqueTest.php`
- Test: `tests/Feature/Coach/HistoriqueTest.php`

**Interfaces:**
- Consumes: `App\Models\Pointage` (Task 3).
- Produces: routes `admin.pointages.index` (GET, filterable by personne/période), `admin.pointages.corriger` (PATCH, motif obligatoire), `coach.historique` (GET, own pointages only).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Admin/PointageHistoriqueTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from the admin pointage history', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.pointages.index'))->assertForbidden();
});

it('lets the admin list all pointages', function () {
    Pointage::factory()->count(3)->create();

    $this->actingAs($this->admin)->get(route('admin.pointages.index'))->assertOk();
});

it('lets the admin filter pointages by fille', function () {
    $fille = Fille::factory()->create();
    Pointage::factory()->create(['pointable_type' => Fille::class, 'pointable_id' => $fille->id]);
    Pointage::factory()->create();

    $response = $this->actingAs($this->admin)->get(route('admin.pointages.index', [
        'pointable_type' => Fille::class,
        'pointable_id' => $fille->id,
    ]));

    $response->assertOk();
});

it('lets the admin correct a pointage with a mandatory motif', function () {
    $pointage = Pointage::factory()->create(['statut_ponctualite' => 'en_retard', 'minutes_retard' => 10]);

    $response = $this->actingAs($this->admin)->patch(route('admin.pointages.corriger', $pointage), [
        'statut_ponctualite' => 'a_l_heure',
        'motif' => 'Erreur de pointage, la fille est arrivée avant le début.',
    ]);

    $response->assertRedirect();
    $pointage->refresh();
    expect($pointage->statut_ponctualite->value)->toBe('a_l_heure');
    expect($pointage->corrige_par_user_id)->toBe($this->admin->id);
    expect($pointage->motif_correction)->not->toBeNull();
});

it('rejects a correction without a motif', function () {
    $pointage = Pointage::factory()->create();

    $response = $this->actingAs($this->admin)->patch(route('admin.pointages.corriger', $pointage), [
        'statut_ponctualite' => 'absent',
        'motif' => '',
    ]);

    $response->assertSessionHasErrors('motif');
});
```

`tests/Feature/Coach/HistoriqueTest.php`:

```php
<?php

use App\Models\Coach;
use App\Models\Fille;
use App\Models\Pointage;

it('shows a coach only their own pointage history', function () {
    $coach = Coach::factory()->create();
    Pointage::factory()->create(['pointable_type' => Coach::class, 'pointable_id' => $coach->id]);
    Pointage::factory()->create(['pointable_type' => Fille::class]);

    $response = $this->actingAs($coach->user)->get(route('coach.historique'));

    $response->assertOk();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/pest tests/Feature/Admin/PointageHistoriqueTest.php tests/Feature/Coach/HistoriqueTest.php
```

Expected: FAIL — routes and controller actions don't exist yet.

- [ ] **Step 3: Create the correction request**

`app/Http/Requests/Admin/CorrigerPointageRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrigerPointageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut_ponctualite' => ['required', Rule::in(['a_l_heure', 'en_retard', 'retard_fort', 'absent'])],
            'motif' => ['required', 'string', 'min:5'],
        ];
    }
}
```

- [ ] **Step 4: Create the admin pointage controller**

`app/Http/Controllers/Admin/PointageController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CorrigerPointageRequest;
use App\Models\Pointage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PointageController extends Controller
{
    public function index(Request $request): View
    {
        $query = Pointage::with(['seance', 'pointable']);

        if ($request->filled('pointable_type') && $request->filled('pointable_id')) {
            $query->where('pointable_type', $request->string('pointable_type'))
                ->where('pointable_id', $request->integer('pointable_id'));
        }

        $pointages = $query->orderByDesc('pointe_a')->paginate(30);

        return view('admin.pointages.index', compact('pointages'));
    }

    public function corriger(CorrigerPointageRequest $request, Pointage $pointage): RedirectResponse
    {
        $pointage->update([
            'statut_ponctualite' => $request->validated('statut_ponctualite'),
            'corrige_par_user_id' => $request->user()->id,
            'motif_correction' => $request->validated('motif'),
        ]);

        return redirect()->route('admin.pointages.index')->with('message', 'Pointage corrigé.');
    }
}
```

- [ ] **Step 5: Add the `historique` action to the coach pointage controller**

In `app/Http/Controllers/Coach/PointageController.php`, add:

```php
public function historique(Request $request): View
{
    $coach = $request->user()->coach;

    $pointages = \App\Models\Pointage::where('pointable_type', \App\Models\Coach::class)
        ->where('pointable_id', $coach?->id)
        ->with('seance')
        ->orderByDesc('pointe_a')
        ->paginate(30);

    return view('coach.historique', compact('pointages'));
}
```

- [ ] **Step 6: Register the routes**

In `routes/web.php`, inside the existing `admin.` group:

```php
use App\Http\Controllers\Admin\PointageController as AdminPointageController;

Route::get('pointages', [AdminPointageController::class, 'index'])->name('pointages.index');
Route::patch('pointages/{pointage}/corriger', [AdminPointageController::class, 'corriger'])->name('pointages.corriger');
```

Inside the existing `coach.` group:

```php
Route::get('mon-historique', [CoachPointageController::class, 'historique'])->name('historique');
```

- [ ] **Step 7: Create the views**

`resources/views/admin/pointages/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Historique des pointages</h1>
    </x-slot>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Personne</th>
                <th>Arrivée</th>
                <th>Statut</th>
                <th>Source</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pointages as $pointage)
                <tr>
                    <td>{{ $pointage->seance->date->format('d/m/Y') }}</td>
                    <td>
                        @if ($pointage->pointable_type === \App\Models\Fille::class)
                            {{ $pointage->pointable->prenom }} {{ $pointage->pointable->nom }}
                        @else
                            {{ $pointage->pointable->user->name }}
                        @endif
                    </td>
                    <td>{{ $pointage->pointe_a?->format('H:i') ?? '—' }}</td>
                    <td>{{ $pointage->statut_ponctualite->value }}</td>
                    <td>{{ $pointage->source->value }}</td>
                    <td>
                        <form action="{{ route('admin.pointages.corriger', $pointage) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <select name="statut_ponctualite">
                                <option value="a_l_heure">À l'heure</option>
                                <option value="en_retard">En retard</option>
                                <option value="retard_fort">Retard fort</option>
                                <option value="absent">Absent</option>
                            </select>
                            <input type="text" name="motif" placeholder="Motif de la correction" required>
                            <button type="submit">Corriger</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $pointages->links() }}
</x-app-layout>
```

`resources/views/coach/historique.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Mon historique de ponctualité</h1>
    </x-slot>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Arrivée</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pointages as $pointage)
                <tr>
                    <td>{{ $pointage->seance->date->format('d/m/Y') }}</td>
                    <td>{{ $pointage->pointe_a?->format('H:i') ?? '—' }}</td>
                    <td>{{ $pointage->statut_ponctualite->value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $pointages->links() }}
</x-app-layout>
```

- [ ] **Step 8: Run the tests to verify they pass**

```bash
vendor/bin/pest tests/Feature/Admin/PointageHistoriqueTest.php tests/Feature/Coach/HistoriqueTest.php
vendor/bin/pest
vendor/bin/pint --test
```

Expected: PASS, full suite green, Pint clean.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add pointage history and admin correction with mandatory motif"
```

---

## End of plan checklist

- [ ] `vendor/bin/pest` passes in full.
- [ ] `vendor/bin/pint --test` reports no issues.
- [ ] No inline `style="..."` was introduced anywhere in the Blade views written in this plan (the kiosk views use Tailwind classes only).
- [ ] `php artisan seances:generer` run manually creates the expected séances for at least one active planning slot, and running it twice does not duplicate them.
- [ ] A coach can log in, see their upcoming/current séance, mark a fille present, backdate a pointage, and clôturer.
- [ ] The kiosk (`/kiosque`) works without authentication, identifies a known PIN, records a self-pointage, and rejects an unknown PIN without leaking whether any particular PIN exists (same generic error either way).
- [ ] The non-conflict rule holds in both directions: a self-pointage blocks a later coach pointage for the same person/séance, and vice versa.
- [ ] The admin calendar shows the correct taux de présence for a clôturée séance, and `—` for anything not yet clôturée.
- [ ] An admin can correct a pointage only with a motif; the correction is visible in `corrige_par_user_id`/`motif_correction`.
- [ ] Next plan: **Prestations & paiements** (création de prestation, déclaration au kiosque, validation admin) — do not start it until this plan's checklist above is fully green.
