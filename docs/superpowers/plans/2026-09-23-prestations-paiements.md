# Prestations & Paiements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add prestation (gig) creation with fille assignment, kiosk self-declaration of cachet (performance fee) receipt, admin validation/correction of that declaration, the Caisse CAFAB expense-creation integration that a validated cachet triggers, and the admin payments overview — the third increment of Présence & Paiements CAFAB, built on the merged Fondations and Planning & Pointage plans.

**Architecture:** A `Prestation` (gig) is created by the Admin with a default cachet amount and a set of assigned `Fille` rows, each producing a `Cachet` row (per-fille amount, adjustable). A `Cachet` moves through a small state machine — `du` → `declaree_payee`/`declaree_non_payee` (fille, kiosk, after the prestation's date, reversible until validated) → `validee_payee` (Admin, terminal, triggers the Caisse CAFAB call) — or sideways to `annule` (when its prestation is cancelled, but never once `validee_payee`). One service, `CachetService`, owns every transition, exactly as `PointageService` owns pointage transitions in the previous plan. This plan spans **two separate Laravel repositories**: `presence-paiement-cafab` (this app) and `caisse-depenses` (Caisse CAFAB) — Task 4 builds the receiving API endpoint in `caisse-depenses`, which has none today; everything else is in `presence-paiement-cafab`. Each task's **Files** section states which repository its paths are relative to.

**Tech Stack:** Laravel 12, PHP 8.3+ (`presence-paiement-cafab`) / PHP 8.2+ (`caisse-depenses`), Blade, Alpine.js, Pest, Laravel Pint — all established by the Fondations plan. `caisse-depenses` currently has **no API routing at all** (no `routes/api.php`, no `api` key in `bootstrap/app.php`, no Sanctum/Passport, no dépense-specific model — it models money movements as a single `Operation` table with a `type` column). Task 4 builds the minimum needed for this one integration from scratch, deliberately not adopting Sanctum for a single fixed service credential.

**Spec:** `C:\wamp64\www\caisse_cafab\documentations\presence-paiement-cafab\` — `technum_specifications-fonctionnelles_presence-paiement-cafab_20260922.docx` §4 "Prestations et cachets" and §6 "Intégration avec Caisse CAFAB", `technum_regles-metier_presence-paiement-cafab_20260922.docx` §3 "Prestations et cachets" and §5 "Intégration avec Caisse CAFAB", `technum_note-cadrage_presence-paiement-cafab_20260922.docx` "Lien avec Caisse CAFAB". Builds directly on `docs/superpowers/plans/2026-09-22-fondations.md` and `docs/superpowers/plans/2026-09-22-planning-pointage.md` (both merged: roles, `Coach`/`Fille` roster, `PinGenerator`, `SecurityHeaders`, kiosk identification, `Seance`/`Pointage`).

## Global Constraints

- `presence-paiement-cafab`: PHP 8.3+, Laravel 12 (unchanged from Fondations). `caisse-depenses`: PHP 8.2+, Laravel 12.
- No inline `style="..."` in Blade views in either repo — both already enforce a CSP with `style-src 'self'`.
- **Non-duplication rule (binding, règles métier §3.3):** a validated cachet triggers at most one dépense creation in Caisse CAFAB, ever — including across retries after a failed call. Enforced by a deterministic, stable `external_reference` (`cachet-{id}`) that Caisse CAFAB's endpoint treats as an idempotency key: a second request with the same reference returns the existing operation instead of creating a new one.
- **Correction always requires a motif**, on the same principle as `PointageService`'s admin correction and `caisse-depenses`' own operation-correction rule — never a silent overwrite.
- **Désactivation/Annulation, never delete:** cancelling a prestation moves its non-finalized cachets to `annule`; a cachet already `validee_payee` is left untouched (règles métier §3.4) — its dépense already exists in Caisse CAFAB and is out of this tool's reach from that point on.
- A cachet becomes immutable (no correction, no montant adjustment, no re-declaration) once `validee_payee` or `annule` — this is enforced in `CachetService`, the one place every entry point (kiosk, Admin) goes through, not duplicated per controller.
- A fille can only declare for **herself**, and only for a prestation whose date has already passed (règles métier §3.2) — a Coach identified at the kiosk never sees the déclaration screen.
- The Caisse CAFAB service token is a single dedicated credential for this integration, distinct from any user account (note de cadrage, "Lien avec Caisse CAFAB") — never logged, read from environment only.
- Money fields are `decimal(10,2)` in both repos, matching `caisse-depenses`' existing `operations.montant` column — no rounding surprises when the amount crosses the wire.

---

## Task 1: Prestations & Cachets — schema, models, admin création/affectation/annulation

**Repository:** `presence-paiement-cafab`

**Files:**
- Create: `app/Enums/StatutPrestation.php`
- Create: `app/Enums/StatutCachet.php`
- Create: `database/migrations/xxxx_xx_xx_xxxxxx_create_prestations_table.php`
- Create: `database/migrations/xxxx_xx_xx_xxxxxx_create_cachets_table.php`
- Create: `app/Models/Prestation.php`
- Create: `app/Models/Cachet.php`
- Create: `database/factories/PrestationFactory.php`
- Create: `database/factories/CachetFactory.php`
- Create: `app/Http/Controllers/Admin/PrestationController.php`
- Create: `app/Http/Requests/Admin/StorePrestationRequest.php`
- Modify: `routes/web.php` (add `admin.prestations.*` inside the existing `admin.` group)
- Create: `resources/views/admin/prestations/index.blade.php`
- Create: `resources/views/admin/prestations/create.blade.php`
- Create: `resources/views/admin/prestations/show.blade.php`
- Modify: `resources/views/layouts/navigation.blade.php` (add a "Prestations" nav link, desktop + responsive)
- Test: `tests/Unit/Models/PrestationTest.php`
- Test: `tests/Unit/Models/CachetTest.php`
- Test: `tests/Feature/Admin/PrestationManagementTest.php`

**Interfaces:**
- Consumes: `App\Models\Fille`, `App\Enums\UserRole` (Fondations).
- Produces: `App\Enums\StatutPrestation` (`Active='active'`, `Annulee='annulee'`). `App\Enums\StatutCachet` (`Du='du'`, `DeclareePayee='declaree_payee'`, `DeclareeNonPayee='declaree_non_payee'`, `ValideePayee='validee_payee'`, `Annule='annule'`). `Prestation` model (fields: `titre`, `lieu`, `date`, `montant_defaut`, `statut`), `Prestation::cachets(): HasMany`, `Prestation::estPassee(): bool`. `Cachet` model (fields: `prestation_id`, `fille_id`, `montant`, `statut`, `declaree_at`, `validee_at`, `valide_par_user_id`, `corrige_par_user_id`, `motif_correction`, `depense_creee_at`, `caisse_cafab_reference`, `depense_erreur`), `Cachet::prestation(): BelongsTo`, `Cachet::fille(): BelongsTo`, `Cachet::estFinalise(): bool` (true when `statut` is `ValideePayee` or `Annule` — Tasks 2, 3 and 5 all gate on this). Route `admin.prestations.show` (later tasks add cachet actions to this same view).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Models/PrestationTest.php`:

```php
<?php

use App\Enums\StatutPrestation;
use App\Models\Cachet;
use App\Models\Prestation;
use Illuminate\Support\Carbon;

it('casts statut to the StatutPrestation enum and defaults to active', function () {
    $prestation = Prestation::factory()->create();

    expect($prestation->statut)->toBe(StatutPrestation::Active);
});

it('has many cachets', function () {
    $prestation = Prestation::factory()->create();
    Cachet::factory()->count(2)->create(['prestation_id' => $prestation->id]);

    expect($prestation->cachets)->toHaveCount(2);
});

it('reports estPassee only once its date is strictly before today', function () {
    Carbon::setTestNow('2026-09-23 10:00:00');

    $hier = Prestation::factory()->create(['date' => '2026-09-22']);
    $aujourdhui = Prestation::factory()->create(['date' => '2026-09-23']);

    expect($hier->estPassee())->toBeTrue();
    expect($aujourdhui->estPassee())->toBeFalse();

    Carbon::setTestNow();
});
```

`tests/Unit/Models/CachetTest.php`:

```php
<?php

use App\Enums\StatutCachet;
use App\Models\Cachet;

it('casts statut to the StatutCachet enum and defaults to du', function () {
    $cachet = Cachet::factory()->create();

    expect($cachet->statut)->toBe(StatutCachet::Du);
});

it('belongs to a prestation and a fille', function () {
    $cachet = Cachet::factory()->create();

    expect($cachet->prestation)->not->toBeNull();
    expect($cachet->fille)->not->toBeNull();
});

it('rejects a second cachet for the same fille on the same prestation', function () {
    $cachet = Cachet::factory()->create();

    Cachet::factory()->create([
        'prestation_id' => $cachet->prestation_id,
        'fille_id' => $cachet->fille_id,
    ]);
})->throws(\Illuminate\Database\QueryException::class);

it('reports estFinalise only for validee_payee or annule', function () {
    $du = Cachet::factory()->create(['statut' => StatutCachet::Du]);
    $declaree = Cachet::factory()->create(['statut' => StatutCachet::DeclareePayee]);
    $validee = Cachet::factory()->create(['statut' => StatutCachet::ValideePayee]);
    $annule = Cachet::factory()->create(['statut' => StatutCachet::Annule]);

    expect($du->estFinalise())->toBeFalse();
    expect($declaree->estFinalise())->toBeFalse();
    expect($validee->estFinalise())->toBeTrue();
    expect($annule->estFinalise())->toBeTrue();
});
```

`tests/Feature/Admin/PrestationManagementTest.php`:

```php
<?php

use App\Enums\StatutCachet;
use App\Enums\StatutPrestation;
use App\Enums\UserRole;
use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Prestation;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from managing prestations', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.prestations.index'))->assertForbidden();
});

it('lets the admin list prestations', function () {
    Prestation::factory()->count(2)->create();

    $this->actingAs($this->admin)->get(route('admin.prestations.index'))->assertOk();
});

it('lets the admin create a prestation and affect filles with per-fille montant overrides', function () {
    $fille1 = Fille::factory()->create();
    $fille2 = Fille::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('admin.prestations.store'), [
        'titre' => 'Spectacle de fin d\'année',
        'lieu' => 'Palais des Congrès',
        'date' => now()->addWeek()->toDateString(),
        'montant_defaut' => 5000,
        'fille_ids' => [$fille1->id, $fille2->id],
        'montants' => [$fille2->id => 7500],
    ]);

    $response->assertRedirect(route('admin.prestations.index'));

    $prestation = Prestation::where('titre', 'Spectacle de fin d\'année')->firstOrFail();
    expect($prestation->cachets)->toHaveCount(2);
    expect(Cachet::where('prestation_id', $prestation->id)->where('fille_id', $fille1->id)->first()->montant)->toBe('5000.00');
    expect(Cachet::where('prestation_id', $prestation->id)->where('fille_id', $fille2->id)->first()->montant)->toBe('7500.00');
    expect(Cachet::where('prestation_id', $prestation->id)->first()->statut)->toBe(StatutCachet::Du);
});

it('lets the admin view a prestation with its cachets', function () {
    $prestation = Prestation::factory()->create();
    Cachet::factory()->create(['prestation_id' => $prestation->id]);

    $this->actingAs($this->admin)->get(route('admin.prestations.show', $prestation))->assertOk();
});

it('cancelling a prestation annule its non-finalized cachets but leaves validated ones untouched', function () {
    $prestation = Prestation::factory()->create();
    $enAttente = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::Du]);
    $validee = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::ValideePayee]);

    $this->actingAs($this->admin)->patch(route('admin.prestations.annuler', $prestation))
        ->assertRedirect(route('admin.prestations.index'));

    expect($prestation->fresh()->statut)->toBe(StatutPrestation::Annulee);
    expect($enAttente->fresh()->statut)->toBe(StatutCachet::Annule);
    expect($validee->fresh()->statut)->toBe(StatutCachet::ValideePayee);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/pest tests/Unit/Models/PrestationTest.php tests/Unit/Models/CachetTest.php tests/Feature/Admin/PrestationManagementTest.php
```

Expected: FAIL — `Prestation`, `Cachet`, the enums, the controller, and the routes don't exist yet.

- [ ] **Step 3: Create the enums**

`app/Enums/StatutPrestation.php`:

```php
<?php

namespace App\Enums;

enum StatutPrestation: string
{
    case Active = 'active';
    case Annulee = 'annulee';
}
```

`app/Enums/StatutCachet.php`:

```php
<?php

namespace App\Enums;

enum StatutCachet: string
{
    case Du = 'du';
    case DeclareePayee = 'declaree_payee';
    case DeclareeNonPayee = 'declaree_non_payee';
    case ValideePayee = 'validee_payee';
    case Annule = 'annule';
}
```

- [ ] **Step 4: Create the migrations and models**

```bash
php artisan make:migration create_prestations_table
php artisan make:migration create_cachets_table
```

`database/migrations/xxxx_xx_xx_xxxxxx_create_prestations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestations', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->string('lieu');
            $table->date('date');
            $table->decimal('montant_defaut', 10, 2);
            $table->string('statut', 20)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestations');
    }
};
```

`database/migrations/xxxx_xx_xx_xxxxxx_create_cachets_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cachets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestation_id')->constrained()->restrictOnDelete();
            $table->foreignId('fille_id')->constrained()->restrictOnDelete();
            $table->decimal('montant', 10, 2);
            $table->string('statut', 20)->default('du');
            $table->timestamp('declaree_at')->nullable();
            $table->timestamp('validee_at')->nullable();
            $table->foreignId('valide_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('corrige_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motif_correction')->nullable();
            $table->timestamp('depense_creee_at')->nullable();
            $table->string('caisse_cafab_reference')->nullable();
            $table->text('depense_erreur')->nullable();
            $table->timestamps();

            $table->unique(['prestation_id', 'fille_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cachets');
    }
};
```

`app/Models/Prestation.php`:

```php
<?php

namespace App\Models;

use App\Enums\StatutPrestation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Prestation extends Model
{
    use HasFactory;

    protected $fillable = ['titre', 'lieu', 'date', 'montant_defaut', 'statut'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'montant_defaut' => 'decimal:2',
            'statut' => StatutPrestation::class,
        ];
    }

    public function cachets(): HasMany
    {
        return $this->hasMany(Cachet::class);
    }

    public function estPassee(): bool
    {
        return $this->date->lt(Carbon::today());
    }
}
```

`app/Models/Cachet.php`:

```php
<?php

namespace App\Models;

use App\Enums\StatutCachet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cachet extends Model
{
    use HasFactory;

    protected $fillable = [
        'prestation_id', 'fille_id', 'montant', 'statut',
        'declaree_at', 'validee_at', 'valide_par_user_id',
        'corrige_par_user_id', 'motif_correction',
        'depense_creee_at', 'caisse_cafab_reference', 'depense_erreur',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'statut' => StatutCachet::class,
            'declaree_at' => 'datetime',
            'validee_at' => 'datetime',
            'depense_creee_at' => 'datetime',
        ];
    }

    public function prestation(): BelongsTo
    {
        return $this->belongsTo(Prestation::class);
    }

    public function fille(): BelongsTo
    {
        return $this->belongsTo(Fille::class);
    }

    public function valideParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par_user_id');
    }

    public function corrigeParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrige_par_user_id');
    }

    public function estFinalise(): bool
    {
        return in_array($this->statut, [StatutCachet::ValideePayee, StatutCachet::Annule], true);
    }
}
```

- [ ] **Step 5: Create the factories**

`database/factories/PrestationFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Prestation;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrestationFactory extends Factory
{
    protected $model = Prestation::class;

    public function definition(): array
    {
        return [
            'titre' => $this->faker->sentence(3),
            'lieu' => $this->faker->city(),
            'date' => now()->addWeek()->toDateString(),
            'montant_defaut' => 5000,
            'statut' => 'active',
        ];
    }
}
```

`database/factories/CachetFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Prestation;
use Illuminate\Database\Eloquent\Factories\Factory;

class CachetFactory extends Factory
{
    protected $model = Cachet::class;

    public function definition(): array
    {
        return [
            'prestation_id' => Prestation::factory(),
            'fille_id' => Fille::factory(),
            'montant' => 5000,
            'statut' => 'du',
        ];
    }
}
```

- [ ] **Step 6: Create the form request**

`app/Http/Requests/Admin/StorePrestationRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePrestationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'lieu' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'montant_defaut' => ['required', 'numeric', 'gt:0'],
            'fille_ids' => ['required', 'array', 'min:1'],
            'fille_ids.*' => ['integer', 'exists:filles,id'],
            'montants' => ['nullable', 'array'],
            'montants.*' => ['nullable', 'numeric', 'gt:0'],
        ];
    }
}
```

- [ ] **Step 7: Create the controller**

`app/Http/Controllers/Admin/PrestationController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutCachet;
use App\Enums\StatutPrestation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePrestationRequest;
use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Prestation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PrestationController extends Controller
{
    public function index(): View
    {
        $prestations = Prestation::withCount('cachets')->orderByDesc('date')->paginate(20);

        return view('admin.prestations.index', compact('prestations'));
    }

    public function create(): View
    {
        $filles = Fille::where('statut', 'actif')->orderBy('nom')->get();

        return view('admin.prestations.create', compact('filles'));
    }

    public function store(StorePrestationRequest $request): RedirectResponse
    {
        $prestation = Prestation::create([
            'titre' => $request->validated('titre'),
            'lieu' => $request->validated('lieu'),
            'date' => $request->validated('date'),
            'montant_defaut' => $request->validated('montant_defaut'),
        ]);

        $montants = $request->validated('montants') ?? [];

        foreach ($request->validated('fille_ids') as $filleId) {
            Cachet::create([
                'prestation_id' => $prestation->id,
                'fille_id' => $filleId,
                'montant' => $montants[$filleId] ?? $request->validated('montant_defaut'),
                'statut' => StatutCachet::Du,
            ]);
        }

        return redirect()->route('admin.prestations.index')->with('message', 'Prestation créée.');
    }

    public function show(Prestation $prestation): View
    {
        $prestation->load(['cachets.fille', 'cachets.valideParUser', 'cachets.corrigeParUser']);

        return view('admin.prestations.show', compact('prestation'));
    }

    public function annuler(Prestation $prestation): RedirectResponse
    {
        $prestation->update(['statut' => StatutPrestation::Annulee]);

        $prestation->cachets()
            ->whereNotIn('statut', [StatutCachet::ValideePayee, StatutCachet::Annule])
            ->update(['statut' => StatutCachet::Annule]);

        return redirect()->route('admin.prestations.index')->with('message', 'Prestation annulée.');
    }
}
```

- [ ] **Step 8: Register the routes**

In `routes/web.php`, inside the existing `admin.` group:

```php
use App\Http\Controllers\Admin\PrestationController;

Route::resource('prestations', PrestationController::class)->only(['index', 'create', 'store', 'show']);
Route::patch('prestations/{prestation}/annuler', [PrestationController::class, 'annuler'])
    ->name('prestations.annuler');
```

- [ ] **Step 9: Create the views**

`resources/views/admin/prestations/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Prestations</h1>
    </x-slot>

    <a href="{{ route('admin.prestations.create') }}">Créer une prestation</a>

    <table>
        <thead>
            <tr>
                <th>Titre</th>
                <th>Lieu</th>
                <th>Date</th>
                <th>Filles affectées</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($prestations as $prestation)
                <tr>
                    <td>{{ $prestation->titre }}</td>
                    <td>{{ $prestation->lieu }}</td>
                    <td>{{ $prestation->date->format('d/m/Y') }}</td>
                    <td>{{ $prestation->cachets_count }}</td>
                    <td>{{ $prestation->statut->value }}</td>
                    <td>
                        <a href="{{ route('admin.prestations.show', $prestation) }}">Voir</a>
                        @if ($prestation->statut->value === 'active')
                            <form action="{{ route('admin.prestations.annuler', $prestation) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit">Annuler</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $prestations->links() }}
</x-app-layout>
```

`resources/views/admin/prestations/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Créer une prestation</h1>
    </x-slot>

    <form action="{{ route('admin.prestations.store') }}" method="POST">
        @csrf

        <label for="titre">Titre</label>
        <input id="titre" name="titre" type="text" value="{{ old('titre') }}" required>
        @error('titre') <p>{{ $message }}</p> @enderror

        <label for="lieu">Lieu</label>
        <input id="lieu" name="lieu" type="text" value="{{ old('lieu') }}" required>
        @error('lieu') <p>{{ $message }}</p> @enderror

        <label for="date">Date</label>
        <input id="date" name="date" type="date" value="{{ old('date') }}" required>
        @error('date') <p>{{ $message }}</p> @enderror

        <label for="montant_defaut">Montant par défaut du cachet</label>
        <input id="montant_defaut" name="montant_defaut" type="number" step="0.01" value="{{ old('montant_defaut') }}" required>
        @error('montant_defaut') <p>{{ $message }}</p> @enderror

        <fieldset>
            <legend>Filles participantes</legend>
            @foreach ($filles as $fille)
                <div>
                    <label>
                        <input type="checkbox" name="fille_ids[]" value="{{ $fille->id }}">
                        {{ $fille->prenom }} {{ $fille->nom }}
                    </label>
                    <label>
                        Montant (optionnel, sinon le montant par défaut)
                        <input type="number" step="0.01" name="montants[{{ $fille->id }}]">
                    </label>
                </div>
            @endforeach
        </fieldset>
        @error('fille_ids') <p>{{ $message }}</p> @enderror

        <button type="submit">Créer</button>
    </form>
</x-app-layout>
```

`resources/views/admin/prestations/show.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>{{ $prestation->titre }}</h1>
    </x-slot>

    <p>{{ $prestation->lieu }} — {{ $prestation->date->format('d/m/Y') }} — statut : {{ $prestation->statut->value }}</p>

    <table>
        <thead>
            <tr>
                <th>Fille</th>
                <th>Montant</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($prestation->cachets as $cachet)
                <tr>
                    <td>{{ $cachet->fille->prenom }} {{ $cachet->fille->nom }}</td>
                    <td>{{ $cachet->montant }}</td>
                    <td>{{ $cachet->statut->value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-app-layout>
```

- [ ] **Step 10: Add the nav link**

In `resources/views/layouts/navigation.blade.php`, inside the desktop `@if (auth()->user()->role === \App\Enums\UserRole::Admin)` block, right after the `admin.pointages.*` link:

```blade
<x-nav-link :href="route('admin.prestations.index')" :active="request()->routeIs('admin.prestations.*')">
    {{ __('Prestations') }}
</x-nav-link>
```

And the matching responsive block, right after the responsive `admin.pointages.*` link:

```blade
<x-responsive-nav-link :href="route('admin.prestations.index')" :active="request()->routeIs('admin.prestations.*')">
    {{ __('Prestations') }}
</x-responsive-nav-link>
```

- [ ] **Step 11: Run the tests to verify they pass**

```bash
php artisan migrate
vendor/bin/pest tests/Unit/Models/PrestationTest.php tests/Unit/Models/CachetTest.php tests/Feature/Admin/PrestationManagementTest.php
vendor/bin/pest
```

Expected: PASS, full suite green.

- [ ] **Step 12: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add prestations and cachets (creation, affectation, annulation)"
```

---

## Task 2: Déclaration du cachet en mode kiosque

**Repository:** `presence-paiement-cafab`

**Files:**
- Create: `app/Exceptions/CachetException.php`
- Create: `app/Services/CachetService.php`
- Create: `app/Http/Controllers/Kiosque/CachetController.php`
- Modify: `routes/web.php` (add `kiosque.cachets.*` inside the existing `kiosque.` group)
- Modify: `resources/views/kiosque/menu.blade.php` (show a "Déclarer mon cachet" button only when the identified person is a Fille with at least one eligible cachet)
- Create: `resources/views/kiosque/cachets/index.blade.php`
- Create: `resources/views/kiosque/cachets/confirmation.blade.php`
- Test: `tests/Unit/Services/CachetServiceTest.php`
- Test: `tests/Feature/Kiosque/CachetDeclarationTest.php`

**Interfaces:**
- Consumes: `App\Models\Cachet`, `App\Models\Prestation`, `App\Enums\StatutCachet` (Task 1), the kiosk session shape (`kiosque.identifie` = `['type' => class-string, 'id' => int]`, `kiosque.nom` = string) established by `Kiosque\IdentificationController` (Planning & Pointage plan).
- Produces: `CachetService::declarer(Cachet $cachet, bool $recu): Cachet` — the one place a declaration's eligibility (prestation passed, cachet not finalised) is enforced; Tasks 3 and 5 extend this same class with `valider()`, `corriger()`, `ajusterMontant()`, `reessayerDepense()`. `CachetException` (`nonEligible()`, `prestationNonEncorePassee()`).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Services/CachetServiceTest.php`:

```php
<?php

use App\Enums\StatutCachet;
use App\Exceptions\CachetException;
use App\Models\Cachet;
use App\Models\Prestation;
use App\Services\CachetService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-23 10:00:00');
    $this->service = new CachetService();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('marks a cachet declaree_payee when the fille declares having received it', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::Du]);

    $result = $this->service->declarer($cachet, true);

    expect($result->statut)->toBe(StatutCachet::DeclareePayee);
    expect($result->declaree_at)->not->toBeNull();
});

it('marks a cachet declaree_non_payee when the fille declares not having received it', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::Du]);

    $result = $this->service->declarer($cachet, false);

    expect($result->statut)->toBe(StatutCachet::DeclareeNonPayee);
});

it('lets the fille change her mind before validation', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::DeclareeNonPayee]);

    $result = $this->service->declarer($cachet, true);

    expect($result->statut)->toBe(StatutCachet::DeclareePayee);
});

it('rejects a declaration for a prestation that has not happened yet', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-23']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::Du]);

    $this->service->declarer($cachet, true);
})->throws(CachetException::class);

it('rejects a declaration for an already validated cachet', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::ValideePayee]);

    $this->service->declarer($cachet, true);
})->throws(CachetException::class);

it('rejects a declaration for a cachet whose prestation was cancelled', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::Annule]);

    $this->service->declarer($cachet, true);
})->throws(CachetException::class);
```

`tests/Feature/Kiosque/CachetDeclarationTest.php`:

```php
<?php

use App\Enums\StatutCachet;
use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Prestation;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-23 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('lists only past, non-finalised cachets for the identified fille', function () {
    $fille = Fille::factory()->create();
    $passee = Prestation::factory()->create(['date' => '2026-09-22']);
    $future = Prestation::factory()->create(['date' => '2026-09-24']);

    $eligible = Cachet::factory()->create(['prestation_id' => $passee->id, 'fille_id' => $fille->id, 'statut' => StatutCachet::Du]);
    Cachet::factory()->create(['prestation_id' => $future->id, 'fille_id' => $fille->id, 'statut' => StatutCachet::Du]);
    Cachet::factory()->create(['prestation_id' => $passee->id, 'fille_id' => $fille->id, 'statut' => StatutCachet::ValideePayee]);

    $this->withSession([
        'kiosque.identifie' => ['type' => Fille::class, 'id' => $fille->id],
        'kiosque.nom' => "{$fille->prenom} {$fille->nom}",
    ])->get(route('kiosque.cachets.index'))
        ->assertOk()
        ->assertSee($passee->titre)
        ->assertDontSee($future->titre);
});

it('redirects to the menu when nobody is identified', function () {
    $this->get(route('kiosque.cachets.index'))->assertRedirect(route('kiosque.menu'));
});

it('lets the identified fille declare having received her cachet', function () {
    $fille = Fille::factory()->create();
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'fille_id' => $fille->id, 'statut' => StatutCachet::Du]);

    $response = $this->withSession([
        'kiosque.identifie' => ['type' => Fille::class, 'id' => $fille->id],
        'kiosque.nom' => "{$fille->prenom} {$fille->nom}",
    ])->post(route('kiosque.cachets.declarer', $cachet), ['recu' => '1']);

    $response->assertOk();
    expect($cachet->fresh()->statut)->toBe(StatutCachet::DeclareePayee);
});

it('blocks declaring for someone else\'s cachet', function () {
    $fille = Fille::factory()->create();
    $autreFille = Fille::factory()->create();
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'fille_id' => $autreFille->id, 'statut' => StatutCachet::Du]);

    $this->withSession([
        'kiosque.identifie' => ['type' => Fille::class, 'id' => $fille->id],
        'kiosque.nom' => "{$fille->prenom} {$fille->nom}",
    ])->post(route('kiosque.cachets.declarer', $cachet), ['recu' => '1'])
        ->assertForbidden();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/pest tests/Unit/Services/CachetServiceTest.php tests/Feature/Kiosque/CachetDeclarationTest.php
```

Expected: FAIL — `CachetService`, `CachetException`, the controller, and the routes don't exist yet.

- [ ] **Step 3: Create the exception and the service**

`app/Exceptions/CachetException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class CachetException extends RuntimeException
{
    public static function nonEligible(): self
    {
        return new self('Ce cachet ne peut plus être déclaré.');
    }

    public static function prestationNonEncorePassee(): self
    {
        return new self("Cette prestation n'a pas encore eu lieu.");
    }
}
```

`app/Services/CachetService.php`:

```php
<?php

namespace App\Services;

use App\Enums\StatutCachet;
use App\Exceptions\CachetException;
use App\Models\Cachet;

class CachetService
{
    public function declarer(Cachet $cachet, bool $recu): Cachet
    {
        if ($cachet->estFinalise()) {
            throw CachetException::nonEligible();
        }

        if (! $cachet->prestation->estPassee()) {
            throw CachetException::prestationNonEncorePassee();
        }

        $cachet->update([
            'statut' => $recu ? StatutCachet::DeclareePayee : StatutCachet::DeclareeNonPayee,
            'declaree_at' => now(),
        ]);

        return $cachet->fresh();
    }
}
```

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/Kiosque/CachetController.php`:

```php
<?php

namespace App\Http\Controllers\Kiosque;

use App\Enums\StatutCachet;
use App\Enums\StatutPrestation;
use App\Exceptions\CachetException;
use App\Http\Controllers\Controller;
use App\Models\Cachet;
use App\Models\Fille;
use App\Services\CachetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CachetController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $identifie = $request->session()->get('kiosque.identifie');

        if (! $identifie || $identifie['type'] !== Fille::class) {
            return redirect()->route('kiosque.menu');
        }

        $fille = Fille::findOrFail($identifie['id']);

        $cachets = Cachet::where('fille_id', $fille->id)
            ->whereIn('statut', [StatutCachet::Du, StatutCachet::DeclareePayee, StatutCachet::DeclareeNonPayee])
            ->whereHas('prestation', fn ($q) => $q->where('statut', StatutPrestation::Active)->where('date', '<', today()))
            ->with('prestation')
            ->get();

        return view('kiosque.cachets.index', [
            'nom' => $request->session()->get('kiosque.nom'),
            'cachets' => $cachets,
        ]);
    }

    public function declarer(Request $request, Cachet $cachet, CachetService $service): View
    {
        $identifie = $request->session()->get('kiosque.identifie');

        abort_if(! $identifie || $identifie['type'] !== Fille::class || $identifie['id'] !== $cachet->fille_id, 403);

        $request->validate(['recu' => ['required', 'boolean']]);

        $nom = $request->session()->get('kiosque.nom');
        $request->session()->forget(['kiosque.identifie', 'kiosque.nom']);

        try {
            $service->declarer($cachet, $request->boolean('recu'));
        } catch (CachetException $e) {
            return view('kiosque.cachets.confirmation', ['nom' => $nom, 'erreur' => $e->getMessage()]);
        }

        return view('kiosque.cachets.confirmation', ['nom' => $nom]);
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, inside the existing `kiosque.` group:

```php
use App\Http\Controllers\Kiosque\CachetController as KiosqueCachetController;

Route::get('cachets', [KiosqueCachetController::class, 'index'])->name('cachets.index');
Route::post('cachets/{cachet}/declarer', [KiosqueCachetController::class, 'declarer'])->name('cachets.declarer');
```

`Admin\CachetController` (Task 3) shares the same short class name in a different namespace — `routes/web.php` will need both imported side by side later, so alias this one now the same way the codebase already aliases `Admin\PointageController`/`Coach\PointageController`. No action needed yet in this task; Task 3 introduces the alias for the Admin one.

- [ ] **Step 6: Update the kiosk menu and create the views**

`resources/views/kiosque/menu.blade.php` (full replacement):

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

        @if ($cachetsEligibles ?? false)
            <a href="{{ route('kiosque.cachets.index') }}" class="block w-full p-6 mt-4 rounded bg-emerald-600 text-lg font-bold">
                Déclarer mon cachet
            </a>
        @endif

        <a href="{{ route('kiosque.home') }}" class="block mt-6 text-sm text-gray-400">
            Ce n'est pas toi ? Touche ici pour revenir en arrière.
        </a>
    </div>
</body>
</html>
```

This new `$cachetsEligibles` variable is provided by `Kiosque\IdentificationController::menu()`, which Task 1's file list did not touch — update it now:

`app/Http/Controllers/Kiosque/IdentificationController.php` (full replacement):

```php
<?php

namespace App\Http\Controllers\Kiosque;

use App\Enums\StatutCachet;
use App\Enums\StatutPrestation;
use App\Http\Controllers\Controller;
use App\Models\Cachet;
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
        $seance = Seance::where('statut', 'en_cours')->whereDate('date', today())->latest('heure_prevue')->first();

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
        $identifie = $request->session()->get('kiosque.identifie');

        if (! $identifie) {
            return redirect()->route('kiosque.home');
        }

        $cachetsEligibles = $identifie['type'] === Fille::class && Cachet::where('fille_id', $identifie['id'])
            ->whereIn('statut', [StatutCachet::Du, StatutCachet::DeclareePayee, StatutCachet::DeclareeNonPayee])
            ->whereHas('prestation', fn ($q) => $q->where('statut', StatutPrestation::Active)->where('date', '<', today()))
            ->exists();

        return view('kiosque.menu', [
            'nom' => $request->session()->get('kiosque.nom'),
            'cachetsEligibles' => $cachetsEligibles,
        ]);
    }
}
```

`resources/views/kiosque/cachets/index.blade.php`:

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
        <h1 class="text-2xl font-bold mb-8">{{ $nom }}</h1>

        @forelse ($cachets as $cachet)
            <div class="mb-6 border border-gray-700 rounded p-4">
                <p class="mb-3">{{ $cachet->prestation->titre }} — {{ $cachet->prestation->date->format('d/m/Y') }}</p>
                <form action="{{ route('kiosque.cachets.declarer', $cachet) }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="recu" value="1">
                    <button type="submit" class="p-4 rounded bg-emerald-600 font-bold">J'ai reçu</button>
                </form>
                <form action="{{ route('kiosque.cachets.declarer', $cachet) }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="recu" value="0">
                    <button type="submit" class="p-4 rounded bg-red-700 font-bold">Je n'ai pas reçu</button>
                </form>
            </div>
        @empty
            <p>Aucun cachet à déclarer pour le moment.</p>
        @endforelse

        <a href="{{ route('kiosque.menu') }}" class="block mt-6 text-sm text-gray-400">Retour</a>
    </div>
</body>
</html>
```

`resources/views/kiosque/cachets/confirmation.blade.php`:

```blade
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pointage — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <meta http-equiv="refresh" content="5;url={{ route('kiosque.home') }}">
</head>
<body class="bg-gray-900 text-white flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-6 text-center">
        @if ($erreur ?? false)
            <h1 class="text-2xl font-bold mb-4">{{ $nom }}, un instant.</h1>
            <p>{{ $erreur }}</p>
        @else
            <h1 class="text-2xl font-bold mb-4">Merci, {{ $nom }}.</h1>
            <p>Déclaration enregistrée.</p>
        @endif
        <p class="mt-6 text-sm text-gray-400">Retour à l'accueil dans quelques secondes…</p>
    </div>
</body>
</html>
```

- [ ] **Step 7: Run the tests to verify they pass**

```bash
php artisan migrate
vendor/bin/pest tests/Unit/Services/CachetServiceTest.php tests/Feature/Kiosque/CachetDeclarationTest.php
vendor/bin/pest
```

Expected: PASS, full suite green.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add kiosk cachet declaration by the fille"
```

---

## Task 3: Validation et correction du cachet par l'Admin

**Repository:** `presence-paiement-cafab`

**Files:**
- Modify: `app/Services/CachetService.php` (add `valider()`, `corriger()`, `ajusterMontant()`)
- Create: `app/Http/Controllers/Admin/CachetController.php`
- Create: `app/Http/Requests/Admin/CorrigerCachetRequest.php`
- Create: `app/Http/Requests/Admin/AjusterMontantCachetRequest.php`
- Modify: `routes/web.php` (add `admin.cachets.*` inside the existing `admin.` group)
- Modify: `resources/views/admin/prestations/show.blade.php` (add valider/corriger/ajuster forms per cachet row)
- Modify: `resources/views/layouts/app.blade.php` (render `session('error')` alongside the existing `session('message')` block)
- Create: `tests/Unit/Services/CachetServiceValidationTest.php`
- Create: `tests/Feature/Admin/CachetValidationTest.php`

**Interfaces:**
- Consumes: `App\Models\Cachet`, `App\Services\CachetService::declarer()` (Task 2).
- Produces: `CachetService::valider(Cachet $cachet, \App\Models\User $admin): Cachet` (sets `validee_payee`, `validee_at`, `valide_par_user_id`; Task 5 wraps this to also call Caisse CAFAB). `CachetService::corriger(Cachet $cachet, \App\Enums\StatutCachet $statut, string $motif, \App\Models\User $admin): Cachet`. `CachetService::ajusterMontant(Cachet $cachet, float $montant): Cachet`. All three throw `CachetException` (reusing `nonEligible()`) when the cachet is already finalised.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Services/CachetServiceValidationTest.php`:

```php
<?php

use App\Enums\StatutCachet;
use App\Enums\UserRole;
use App\Exceptions\CachetException;
use App\Models\Cachet;
use App\Models\Prestation;
use App\Models\User;
use App\Services\CachetService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-23 10:00:00');
    $this->service = new CachetService();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('validates a cachet, recording who and when', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['prestation_id' => $prestation->id, 'statut' => StatutCachet::DeclareePayee]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $result = $this->service->valider($cachet, $admin);

    expect($result->statut)->toBe(StatutCachet::ValideePayee);
    expect($result->valide_par_user_id)->toBe($admin->id);
    expect($result->validee_at)->not->toBeNull();
});

it('rejects validating an already validated cachet', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::ValideePayee]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->service->valider($cachet, $admin);
})->throws(CachetException::class);

it('rejects validating an annule cachet', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::Annule]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->service->valider($cachet, $admin);
})->throws(CachetException::class);

it('corrects a cachet declaration with a motif, recording who', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::DeclareeNonPayee]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $result = $this->service->corriger($cachet, StatutCachet::DeclareePayee, 'La fille a confirmé oralement avoir reçu son cachet.', $admin);

    expect($result->statut)->toBe(StatutCachet::DeclareePayee);
    expect($result->corrige_par_user_id)->toBe($admin->id);
    expect($result->motif_correction)->toBe('La fille a confirmé oralement avoir reçu son cachet.');
});

it('rejects correcting an already validated cachet', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::ValideePayee]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->service->corriger($cachet, StatutCachet::DeclareePayee, 'Motif quelconque.', $admin);
})->throws(CachetException::class);

it('adjusts a cachet montant before validation', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::Du, 'montant' => 5000]);

    $result = $this->service->ajusterMontant($cachet, 7500);

    expect($result->montant)->toBe('7500.00');
});

it('rejects adjusting the montant of an already validated cachet', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::ValideePayee]);

    $this->service->ajusterMontant($cachet, 7500);
})->throws(CachetException::class);
```

`tests/Feature/Admin/CachetValidationTest.php`:

```php
<?php

use App\Enums\StatutCachet;
use App\Enums\UserRole;
use App\Models\Cachet;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from validating a cachet', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::DeclareePayee]);

    $this->actingAs($coach)->patch(route('admin.cachets.valider', $cachet))->assertForbidden();
});

it('lets the admin validate a cachet', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::DeclareePayee]);

    $this->actingAs($this->admin)->patch(route('admin.cachets.valider', $cachet))
        ->assertRedirect(route('admin.prestations.show', $cachet->prestation_id));

    expect($cachet->fresh()->statut)->toBe(StatutCachet::ValideePayee);
});

it('shows an error and does not change statut when validating an already validated cachet', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::ValideePayee]);

    $this->actingAs($this->admin)->patch(route('admin.cachets.valider', $cachet))
        ->assertRedirect(route('admin.prestations.show', $cachet->prestation_id))
        ->assertSessionHas('error');

    expect($cachet->fresh()->statut)->toBe(StatutCachet::ValideePayee);
});

it('lets the admin correct a declaration with a motif', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::DeclareeNonPayee]);

    $this->actingAs($this->admin)->patch(route('admin.cachets.corriger', $cachet), [
        'statut' => 'declaree_payee',
        'motif' => 'La fille a confirmé oralement avoir reçu son cachet.',
    ])->assertRedirect(route('admin.prestations.show', $cachet->prestation_id));

    expect($cachet->fresh()->statut)->toBe(StatutCachet::DeclareePayee);
});

it('requires a motif to correct a declaration', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::DeclareeNonPayee]);

    $this->actingAs($this->admin)->patch(route('admin.cachets.corriger', $cachet), [
        'statut' => 'declaree_payee',
    ])->assertSessionHasErrors('motif');
});

it('lets the admin adjust the montant before validation', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::Du, 'montant' => 5000]);

    $this->actingAs($this->admin)->patch(route('admin.cachets.ajuster-montant', $cachet), ['montant' => 7500])
        ->assertRedirect(route('admin.prestations.show', $cachet->prestation_id));

    expect($cachet->fresh()->montant)->toBe('7500.00');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/pest tests/Unit/Services/CachetServiceValidationTest.php tests/Feature/Admin/CachetValidationTest.php
```

Expected: FAIL — `valider`/`corriger`/`ajusterMontant` don't exist on `CachetService` yet, the controller and routes don't exist.

- [ ] **Step 3: Extend `CachetService`**

`app/Services/CachetService.php` (full replacement):

```php
<?php

namespace App\Services;

use App\Enums\StatutCachet;
use App\Exceptions\CachetException;
use App\Models\Cachet;
use App\Models\User;

class CachetService
{
    public function declarer(Cachet $cachet, bool $recu): Cachet
    {
        if ($cachet->estFinalise()) {
            throw CachetException::nonEligible();
        }

        if (! $cachet->prestation->estPassee()) {
            throw CachetException::prestationNonEncorePassee();
        }

        $cachet->update([
            'statut' => $recu ? StatutCachet::DeclareePayee : StatutCachet::DeclareeNonPayee,
            'declaree_at' => now(),
        ]);

        return $cachet->fresh();
    }

    public function valider(Cachet $cachet, User $admin): Cachet
    {
        if ($cachet->estFinalise()) {
            throw CachetException::nonEligible();
        }

        $cachet->update([
            'statut' => StatutCachet::ValideePayee,
            'validee_at' => now(),
            'valide_par_user_id' => $admin->id,
        ]);

        return $cachet->fresh();
    }

    public function corriger(Cachet $cachet, StatutCachet $statut, string $motif, User $admin): Cachet
    {
        if ($cachet->estFinalise()) {
            throw CachetException::nonEligible();
        }

        $cachet->update([
            'statut' => $statut,
            'corrige_par_user_id' => $admin->id,
            'motif_correction' => $motif,
        ]);

        return $cachet->fresh();
    }

    public function ajusterMontant(Cachet $cachet, float $montant): Cachet
    {
        if ($cachet->estFinalise()) {
            throw CachetException::nonEligible();
        }

        $cachet->update(['montant' => $montant]);

        return $cachet->fresh();
    }
}
```

- [ ] **Step 4: Create the form requests**

`app/Http/Requests/Admin/CorrigerCachetRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrigerCachetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::in(['declaree_payee', 'declaree_non_payee'])],
            'motif' => ['required', 'string', 'min:5'],
        ];
    }
}
```

`app/Http/Requests/Admin/AjusterMontantCachetRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AjusterMontantCachetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'montant' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
```

- [ ] **Step 5: Create the controller**

`app/Http/Controllers/Admin/CachetController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutCachet;
use App\Exceptions\CachetException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AjusterMontantCachetRequest;
use App\Http\Requests\Admin\CorrigerCachetRequest;
use App\Models\Cachet;
use App\Services\CachetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CachetController extends Controller
{
    public function valider(Request $request, Cachet $cachet, CachetService $service): RedirectResponse
    {
        try {
            $service->valider($cachet, $request->user());
        } catch (CachetException $e) {
            return redirect()->route('admin.prestations.show', $cachet->prestation_id)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.prestations.show', $cachet->prestation_id)->with('message', 'Paiement validé.');
    }

    public function corriger(CorrigerCachetRequest $request, Cachet $cachet, CachetService $service): RedirectResponse
    {
        try {
            $service->corriger(
                $cachet,
                StatutCachet::from($request->validated('statut')),
                $request->validated('motif'),
                $request->user()
            );
        } catch (CachetException $e) {
            return redirect()->route('admin.prestations.show', $cachet->prestation_id)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.prestations.show', $cachet->prestation_id)->with('message', 'Déclaration corrigée.');
    }

    public function ajusterMontant(AjusterMontantCachetRequest $request, Cachet $cachet, CachetService $service): RedirectResponse
    {
        try {
            $service->ajusterMontant($cachet, (float) $request->validated('montant'));
        } catch (CachetException $e) {
            return redirect()->route('admin.prestations.show', $cachet->prestation_id)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.prestations.show', $cachet->prestation_id)->with('message', 'Montant ajusté.');
    }
}
```

- [ ] **Step 6: Register the routes**

In `routes/web.php`, inside the existing `admin.` group, after the prestations routes added in Task 1. `Kiosque\CachetController` is already imported in this file as `KiosqueCachetController` (Task 2) — alias this one as `AdminCachetController` the same way, mirroring the existing `Admin\PointageController as AdminPointageController` / `Coach\PointageController as CoachPointageController` pair already in this file:

```php
use App\Http\Controllers\Admin\CachetController as AdminCachetController;

Route::patch('cachets/{cachet}/valider', [AdminCachetController::class, 'valider'])->name('cachets.valider');
Route::patch('cachets/{cachet}/corriger', [AdminCachetController::class, 'corriger'])->name('cachets.corriger');
Route::patch('cachets/{cachet}/montant', [AdminCachetController::class, 'ajusterMontant'])->name('cachets.ajuster-montant');
```

- [ ] **Step 7: Show the error flash message**

`resources/views/layouts/app.blade.php` — add an `@if (session('error'))` block right after the existing `@if (session('message'))` block (full file):

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            @if (session('message'))
                <div class="max-w-7xl mx-auto mt-4 px-4 sm:px-6 lg:px-8">
                    <div class="bg-green-100 border border-green-300 text-green-800 rounded-md px-4 py-3">
                        {{ session('message') }}
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="max-w-7xl mx-auto mt-4 px-4 sm:px-6 lg:px-8">
                    <div class="bg-red-100 border border-red-300 text-red-800 rounded-md px-4 py-3">
                        {{ session('error') }}
                    </div>
                </div>
            @endif

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
```

- [ ] **Step 8: Add the actions to the prestation detail view**

`resources/views/admin/prestations/show.blade.php` (full replacement):

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>{{ $prestation->titre }}</h1>
    </x-slot>

    <p>{{ $prestation->lieu }} — {{ $prestation->date->format('d/m/Y') }} — statut : {{ $prestation->statut->value }}</p>

    <table>
        <thead>
            <tr>
                <th>Fille</th>
                <th>Montant</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($prestation->cachets as $cachet)
                <tr>
                    <td>{{ $cachet->fille->prenom }} {{ $cachet->fille->nom }}</td>
                    <td>{{ $cachet->montant }}</td>
                    <td>{{ $cachet->statut->value }}</td>
                    <td>
                        @unless ($cachet->estFinalise())
                            <form action="{{ route('admin.cachets.ajuster-montant', $cachet) }}" method="POST" class="inline">
                                @csrf
                                @method('PATCH')
                                <input type="number" step="0.01" name="montant" value="{{ $cachet->montant }}">
                                <button type="submit">Ajuster</button>
                            </form>

                            <form action="{{ route('admin.cachets.valider', $cachet) }}" method="POST" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit">Valider le paiement</button>
                            </form>

                            <form action="{{ route('admin.cachets.corriger', $cachet) }}" method="POST" class="inline">
                                @csrf
                                @method('PATCH')
                                <select name="statut">
                                    <option value="declaree_payee">Corriger en : déclarée payée</option>
                                    <option value="declaree_non_payee">Corriger en : déclarée non payée</option>
                                </select>
                                <input type="text" name="motif" placeholder="Motif de la correction (obligatoire)">
                                <button type="submit">Corriger</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-app-layout>
```

- [ ] **Step 9: Run the tests to verify they pass**

```bash
vendor/bin/pest tests/Unit/Services/CachetServiceValidationTest.php tests/Feature/Admin/CachetValidationTest.php
vendor/bin/pest
```

Expected: PASS, full suite green.

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add admin validation and correction of cachets"
```

---

## Task 4: API de création de dépense — côté Caisse CAFAB

**Repository:** `caisse-depenses` — a **separate git repository**. Run every command and commit in this task from `C:\wamp64\www\caisse_cafab\caisse-depenses`, not from `presence-paiement-cafab`.

**Context:** `caisse-depenses` has no API routing today (`bootstrap/app.php`'s `withRouting()` has no `api:` key, there is no `routes/api.php`), no dépense-specific model (money movements are one `operations` table with a `type` column, `App\Enums\OperationType::Depense`/`Entree`), and no service-token authentication (no Sanctum, no Passport, no custom key middleware). This task adds the minimum needed for one dedicated, non-user integration credential to create `type = depense` operations idempotently.

**Files:**
- Create: `database/migrations/xxxx_xx_xx_xxxxxx_add_external_reference_to_operations_table.php`
- Modify: `app/Models/Operation.php` (add `external_reference` to `$fillable`)
- Create: `app/Http/Middleware/EnsureValidServiceToken.php`
- Modify: `bootstrap/app.php` (register `routes/api.php` and the `service.token` middleware alias)
- Create: `routes/api.php`
- Create: `app/Http/Controllers/Api/OperationController.php`
- Create: `app/Http/Requests/Api/StoreDepenseRequest.php`
- Modify: `config/services.php` (add the `presence_paiement_cafab` service-token entry)
- Modify: `.env.example` (document `PRESENCE_PAIEMENT_CAFAB_SERVICE_TOKEN`)
- Test: `tests/Feature/Api/CreerDepenseTest.php`

**Interfaces:**
- Consumes: `App\Models\Operation`, `App\Enums\OperationType` (existing).
- Produces: `POST /api/operations`, header `Authorization: Bearer <token>` checked against `config('services.presence_paiement_cafab.token')`. Request body: `montant` (numeric, required), `date_operation` (date, required), `motif` (string, required, max 255), `categorie` (string, optional, max 100), `external_reference` (string, required — the idempotency key). Response: `201` with `{"id": int, "external_reference": string}` on first creation; `200` with the same shape when `external_reference` already exists (no duplicate row created — this is what Task 5's retry relies on).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Api/CreerDepenseTest.php`:

```php
<?php

use App\Enums\OperationType;
use App\Models\Operation;

beforeEach(function () {
    config(['services.presence_paiement_cafab.token' => 'test-service-token']);
});

it('rejects a request with no token', function () {
    $this->postJson('/api/operations', [
        'montant' => 5000,
        'date_operation' => '2026-09-22',
        'motif' => 'Cachet — Spectacle — Awa Dupont',
        'external_reference' => 'cachet-1',
    ])->assertUnauthorized();
});

it('rejects a request with the wrong token', function () {
    $this->withHeader('Authorization', 'Bearer wrong-token')
        ->postJson('/api/operations', [
            'montant' => 5000,
            'date_operation' => '2026-09-22',
            'motif' => 'Cachet — Spectacle — Awa Dupont',
            'external_reference' => 'cachet-1',
        ])->assertUnauthorized();
});

it('creates a depense operation with a valid token', function () {
    $response = $this->withHeader('Authorization', 'Bearer test-service-token')
        ->postJson('/api/operations', [
            'montant' => 5000,
            'date_operation' => '2026-09-22',
            'motif' => 'Cachet — Spectacle — Awa Dupont',
            'categorie' => 'Prestations',
            'external_reference' => 'cachet-1',
        ]);

    $response->assertCreated();
    $response->assertJsonStructure(['id', 'external_reference']);

    $operation = Operation::where('external_reference', 'cachet-1')->firstOrFail();
    expect($operation->type)->toBe(OperationType::Depense);
    expect((float) $operation->montant)->toBe(5000.0);
});

it('returns the existing operation instead of duplicating on a repeated external_reference', function () {
    $payload = [
        'montant' => 5000,
        'date_operation' => '2026-09-22',
        'motif' => 'Cachet — Spectacle — Awa Dupont',
        'external_reference' => 'cachet-1',
    ];

    $this->withHeader('Authorization', 'Bearer test-service-token')->postJson('/api/operations', $payload)->assertCreated();
    $second = $this->withHeader('Authorization', 'Bearer test-service-token')->postJson('/api/operations', $payload);

    $second->assertOk();
    expect(Operation::where('external_reference', 'cachet-1')->count())->toBe(1);
});

it('rejects a missing external_reference', function () {
    $this->withHeader('Authorization', 'Bearer test-service-token')
        ->postJson('/api/operations', [
            'montant' => 5000,
            'date_operation' => '2026-09-22',
            'motif' => 'Cachet — Spectacle — Awa Dupont',
        ])->assertUnprocessable();
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/pest tests/Feature/Api/CreerDepenseTest.php
```

Expected: FAIL — `/api/operations` doesn't resolve (no `routes/api.php` registered yet).

- [ ] **Step 3: Add the `external_reference` column**

```bash
php artisan make:migration add_external_reference_to_operations_table --table=operations
```

`database/migrations/xxxx_xx_xx_xxxxxx_add_external_reference_to_operations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->string('external_reference')->nullable()->unique()->after('commentaire');
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn('external_reference');
        });
    }
};
```

`app/Models/Operation.php` — add `external_reference` to `$fillable` (the rest of the file is unchanged):

```php
    protected $fillable = [
        'type',
        'montant',
        'date_operation',
        'motif',
        'categorie',
        'commentaire',
        'user_id',
        'external_reference',
    ];
```

- [ ] **Step 4: Create the service-token middleware**

`app/Http/Middleware/EnsureValidServiceToken.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidServiceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.presence_paiement_cafab.token');
        $given = $request->bearerToken();

        abort_if(! $expected || ! $given || ! hash_equals($expected, $given), 401);

        return $next($request);
    }
}
```

- [ ] **Step 5: Register API routing and the middleware alias**

`bootstrap/app.php` (full replacement):

```php
<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureValidServiceToken;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'service.token' => EnsureValidServiceToken::class,
        ]);

        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

- [ ] **Step 6: Create the request, controller and route**

`app/Http/Requests/Api/StoreDepenseRequest.php`:

```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'montant' => ['required', 'numeric', 'gt:0'],
            'date_operation' => ['required', 'date'],
            'motif' => ['required', 'string', 'max:255'],
            'categorie' => ['nullable', 'string', 'max:100'],
            'external_reference' => ['required', 'string', 'max:255'],
        ];
    }
}
```

`app/Http/Controllers/Api/OperationController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Enums\OperationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDepenseRequest;
use App\Models\Operation;
use Illuminate\Http\JsonResponse;

class OperationController extends Controller
{
    public function store(StoreDepenseRequest $request): JsonResponse
    {
        $existante = Operation::where('external_reference', $request->validated('external_reference'))->first();

        if ($existante) {
            return response()->json([
                'id' => $existante->id,
                'external_reference' => $existante->external_reference,
            ], 200);
        }

        $operation = Operation::create([
            ...$request->validated(),
            'type' => OperationType::Depense,
        ]);

        return response()->json([
            'id' => $operation->id,
            'external_reference' => $operation->external_reference,
        ], 201);
    }
}
```

`routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\OperationController;
use Illuminate\Support\Facades\Route;

Route::middleware('service.token')->post('operations', [OperationController::class, 'store']);
```

- [ ] **Step 7: Wire the config and document the env var**

`config/services.php` — add, keeping the existing entries:

```php
    'presence_paiement_cafab' => [
        'token' => env('PRESENCE_PAIEMENT_CAFAB_SERVICE_TOKEN'),
    ],
```

`.env.example` — append:

```
# Service token dedicated to the Présence & Paiements CAFAB integration
# (POST /api/operations). Distinct from any user account. Generate with
# `php artisan tinker --execute="echo Str::random(64);"` and share it
# out of band with whoever configures the other app's CAISSE_CAFAB_API_TOKEN.
PRESENCE_PAIEMENT_CAFAB_SERVICE_TOKEN=
```

- [ ] **Step 8: Run the tests to verify they pass**

```bash
php artisan migrate
vendor/bin/pest tests/Feature/Api/CreerDepenseTest.php
vendor/bin/pest
```

Expected: PASS, full suite green — confirm the pre-existing `Operations` feature tests (web UI) still pass unchanged, since this task only adds a column and a parallel API surface.

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add service-token-authenticated API endpoint to create depense operations"
```

---

## Task 5: Intégration Caisse CAFAB — côté client

**Repository:** `presence-paiement-cafab`

**Files:**
- Create: `app/Exceptions/CaisseCafabException.php`
- Create: `app/Services/CaisseCafabClient.php`
- Modify: `app/Services/CachetService.php` (wire the client into `valider()`, add `reessayerDepense()`)
- Modify: `app/Http/Controllers/Admin/CachetController.php` (add `reessayerDepense()`)
- Modify: `routes/web.php` (add `admin.cachets.reessayer-depense`)
- Modify: `resources/views/admin/prestations/show.blade.php` (show dépense status + a retry button)
- Modify: `config/services.php` (add the `caisse_cafab` client config)
- Modify: `.env.example` (document `CAISSE_CAFAB_API_URL` and `CAISSE_CAFAB_API_TOKEN`)
- Test: `tests/Unit/Services/CaisseCafabClientTest.php`
- Create: `tests/Unit/Services/CachetServiceDepenseTest.php`

**Interfaces:**
- Consumes: `App\Models\Cachet` (Task 1), `CachetService::valider()` (Task 3).
- Produces: `CaisseCafabClient::creerDepense(Cachet $cachet): string` (returns the `external_reference` on success, throws `CaisseCafabException` on any non-2xx or connection failure). `CachetService::reessayerDepense(Cachet $cachet): Cachet`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Services/CaisseCafabClientTest.php`:

```php
<?php

use App\Exceptions\CaisseCafabException;
use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Prestation;
use App\Services\CaisseCafabClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.caisse_cafab.url' => 'https://caisse.cafab.test',
        'services.caisse_cafab.token' => 'test-token',
    ]);
});

it('posts the cachet as a depense and returns the external_reference on success', function () {
    Http::fake(['caisse.cafab.test/*' => Http::response(['id' => 42, 'external_reference' => 'cachet-1'], 201)]);

    $fille = Fille::factory()->create(['nom' => 'Dupont', 'prenom' => 'Awa']);
    $prestation = Prestation::factory()->create(['titre' => 'Spectacle', 'date' => '2026-09-20']);
    $cachet = Cachet::factory()->create(['id' => 1, 'prestation_id' => $prestation->id, 'fille_id' => $fille->id, 'montant' => 5000]);

    $reference = (new CaisseCafabClient())->creerDepense($cachet);

    expect($reference)->toBe('cachet-1');
    Http::assertSent(function ($request) {
        return $request->url() === 'https://caisse.cafab.test/api/operations'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['external_reference'] === 'cachet-1'
            && $request['montant'] === 5000.0
            && $request['date_operation'] === '2026-09-20';
    });
});

it('throws when Caisse CAFAB responds with an error', function () {
    Http::fake(['caisse.cafab.test/*' => Http::response(['message' => 'invalid'], 422)]);

    $cachet = Cachet::factory()->create(['id' => 1]);

    (new CaisseCafabClient())->creerDepense($cachet);
})->throws(CaisseCafabException::class);

it('throws when the connection fails', function () {
    Http::fake(['caisse.cafab.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

    $cachet = Cachet::factory()->create(['id' => 1]);

    (new CaisseCafabClient())->creerDepense($cachet);
})->throws(CaisseCafabException::class);
```

`tests/Unit/Services/CachetServiceDepenseTest.php`:

```php
<?php

use App\Enums\StatutCachet;
use App\Enums\UserRole;
use App\Exceptions\CachetException;
use App\Exceptions\CaisseCafabException;
use App\Models\Cachet;
use App\Models\Prestation;
use App\Models\User;
use App\Services\CachetService;
use App\Services\CaisseCafabClient;
use Mockery;

it('marks the depense as created when Caisse CAFAB accepts it during validation', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['id' => 1, 'prestation_id' => $prestation->id, 'statut' => StatutCachet::DeclareePayee]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $client = Mockery::mock(CaisseCafabClient::class);
    $client->shouldReceive('creerDepense')->once()->with(Mockery::on(fn ($c) => $c->id === $cachet->id))->andReturn('cachet-1');

    $result = (new CachetService($client))->valider($cachet, $admin);

    expect($result->depense_creee_at)->not->toBeNull();
    expect($result->caisse_cafab_reference)->toBe('cachet-1');
    expect($result->depense_erreur)->toBeNull();
});

it('still validates locally, recording the error, when Caisse CAFAB call fails', function () {
    $prestation = Prestation::factory()->create(['date' => '2026-09-22']);
    $cachet = Cachet::factory()->create(['id' => 1, 'prestation_id' => $prestation->id, 'statut' => StatutCachet::DeclareePayee]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $client = Mockery::mock(CaisseCafabClient::class);
    $client->shouldReceive('creerDepense')->once()->andThrow(new CaisseCafabException('Connexion à Caisse CAFAB impossible.'));

    $result = (new CachetService($client))->valider($cachet, $admin);

    expect($result->statut)->toBe(StatutCachet::ValideePayee);
    expect($result->depense_creee_at)->toBeNull();
    expect($result->depense_erreur)->toBe('Connexion à Caisse CAFAB impossible.');
});

it('retries the depense creation and clears the error on success', function () {
    $cachet = Cachet::factory()->create(['id' => 1, 'statut' => StatutCachet::ValideePayee, 'depense_erreur' => 'échec précédent']);

    $client = Mockery::mock(CaisseCafabClient::class);
    $client->shouldReceive('creerDepense')->once()->andReturn('cachet-1');

    $result = (new CachetService($client))->reessayerDepense($cachet);

    expect($result->depense_creee_at)->not->toBeNull();
    expect($result->caisse_cafab_reference)->toBe('cachet-1');
    expect($result->depense_erreur)->toBeNull();
});

it('rejects retrying when the depense was already created', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::ValideePayee, 'depense_creee_at' => now()]);

    (new CachetService())->reessayerDepense($cachet);
})->throws(CachetException::class);

it('rejects retrying a cachet that was never validated', function () {
    $cachet = Cachet::factory()->create(['statut' => StatutCachet::Du]);

    (new CachetService())->reessayerDepense($cachet);
})->throws(CachetException::class);
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/pest tests/Unit/Services/CaisseCafabClientTest.php tests/Unit/Services/CachetServiceDepenseTest.php
```

Expected: FAIL — `CaisseCafabClient`, `CaisseCafabException`, and `CachetService::reessayerDepense()` don't exist yet; `CachetService`'s constructor doesn't accept a client yet.

- [ ] **Step 3: Create the exception and the client**

`app/Exceptions/CaisseCafabException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class CaisseCafabException extends RuntimeException
{
    //
}
```

`app/Services/CaisseCafabClient.php`:

```php
<?php

namespace App\Services;

use App\Exceptions\CaisseCafabException;
use App\Models\Cachet;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CaisseCafabClient
{
    public function creerDepense(Cachet $cachet): string
    {
        $reference = "cachet-{$cachet->id}";
        $url = rtrim((string) config('services.caisse_cafab.url'), '/').'/api/operations';

        try {
            $response = Http::withToken((string) config('services.caisse_cafab.token'))
                ->timeout(10)
                ->post($url, [
                    'montant' => (float) $cachet->montant,
                    'date_operation' => $cachet->prestation->date->toDateString(),
                    'motif' => "Cachet — {$cachet->prestation->titre} — {$cachet->fille->prenom} {$cachet->fille->nom}",
                    'categorie' => 'Prestations',
                    'external_reference' => $reference,
                ]);
        } catch (ConnectionException $e) {
            throw new CaisseCafabException('Connexion à Caisse CAFAB impossible.', previous: $e);
        }

        if ($response->failed()) {
            throw new CaisseCafabException("Caisse CAFAB a répondu avec une erreur ({$response->status()}).");
        }

        return $response->json('external_reference') ?? $reference;
    }
}
```

- [ ] **Step 4: Wire the client into `CachetService`**

`app/Services/CachetService.php` (full replacement):

```php
<?php

namespace App\Services;

use App\Enums\StatutCachet;
use App\Exceptions\CachetException;
use App\Exceptions\CaisseCafabException;
use App\Models\Cachet;
use App\Models\User;

class CachetService
{
    public function __construct(private readonly CaisseCafabClient $caisseCafabClient = new CaisseCafabClient())
    {
    }

    public function declarer(Cachet $cachet, bool $recu): Cachet
    {
        if ($cachet->estFinalise()) {
            throw CachetException::nonEligible();
        }

        if (! $cachet->prestation->estPassee()) {
            throw CachetException::prestationNonEncorePassee();
        }

        $cachet->update([
            'statut' => $recu ? StatutCachet::DeclareePayee : StatutCachet::DeclareeNonPayee,
            'declaree_at' => now(),
        ]);

        return $cachet->fresh();
    }

    public function valider(Cachet $cachet, User $admin): Cachet
    {
        if ($cachet->estFinalise()) {
            throw CachetException::nonEligible();
        }

        $cachet->update([
            'statut' => StatutCachet::ValideePayee,
            'validee_at' => now(),
            'valide_par_user_id' => $admin->id,
        ]);

        $this->declencherDepense($cachet);

        return $cachet->fresh();
    }

    public function corriger(Cachet $cachet, StatutCachet $statut, string $motif, User $admin): Cachet
    {
        if ($cachet->estFinalise()) {
            throw CachetException::nonEligible();
        }

        $cachet->update([
            'statut' => $statut,
            'corrige_par_user_id' => $admin->id,
            'motif_correction' => $motif,
        ]);

        return $cachet->fresh();
    }

    public function ajusterMontant(Cachet $cachet, float $montant): Cachet
    {
        if ($cachet->estFinalise()) {
            throw CachetException::nonEligible();
        }

        $cachet->update(['montant' => $montant]);

        return $cachet->fresh();
    }

    public function reessayerDepense(Cachet $cachet): Cachet
    {
        if ($cachet->statut !== StatutCachet::ValideePayee || $cachet->depense_creee_at !== null) {
            throw CachetException::nonEligible();
        }

        $this->declencherDepense($cachet);

        return $cachet->fresh();
    }

    private function declencherDepense(Cachet $cachet): void
    {
        try {
            $reference = $this->caisseCafabClient->creerDepense($cachet);

            $cachet->update([
                'depense_creee_at' => now(),
                'caisse_cafab_reference' => $reference,
                'depense_erreur' => null,
            ]);
        } catch (CaisseCafabException $e) {
            $cachet->update(['depense_erreur' => $e->getMessage()]);
        }
    }
}
```

- [ ] **Step 5: Add the retry action**

`app/Http/Controllers/Admin/CachetController.php` — add this method (rest of the file from Task 3 is unchanged):

```php
    public function reessayerDepense(Cachet $cachet, CachetService $service): RedirectResponse
    {
        try {
            $service->reessayerDepense($cachet);
        } catch (CachetException $e) {
            return redirect()->route('admin.prestations.show', $cachet->prestation_id)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.prestations.show', $cachet->prestation_id)->with('message', 'Dépense créée.');
    }
```

- [ ] **Step 6: Register the route**

In `routes/web.php`, inside the existing `admin.` group, after the cachet routes added in Task 3. `AdminCachetController` is already imported (Task 3) — reuse it:

```php
Route::patch('cachets/{cachet}/reessayer-depense', [AdminCachetController::class, 'reessayerDepense'])
    ->name('cachets.reessayer-depense');
```

- [ ] **Step 7: Show dépense status and the retry button**

`resources/views/admin/prestations/show.blade.php` — inside the `<td>` actions cell, add this block right after the `@unless ($cachet->estFinalise())` ... `@endunless` block from Task 3 (still inside the same `<td>`):

```blade
                        @if ($cachet->statut->value === 'validee_payee')
                            @if ($cachet->depense_creee_at)
                                <p>Dépense créée dans Caisse CAFAB.</p>
                            @else
                                <p>Dépense non créée : {{ $cachet->depense_erreur }}</p>
                                <form action="{{ route('admin.cachets.reessayer-depense', $cachet) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit">Réessayer</button>
                                </form>
                            @endif
                        @endif
```

- [ ] **Step 8: Wire the config and document the env vars**

`config/services.php` — add, keeping the existing entries:

```php
    'caisse_cafab' => [
        'url' => env('CAISSE_CAFAB_API_URL'),
        'token' => env('CAISSE_CAFAB_API_TOKEN'),
    ],
```

`.env.example` — append:

```
# Caisse CAFAB integration (creates a depense when a cachet is validated).
# CAISSE_CAFAB_API_TOKEN must match caisse-depenses' own
# PRESENCE_PAIEMENT_CAFAB_SERVICE_TOKEN.
CAISSE_CAFAB_API_URL=
CAISSE_CAFAB_API_TOKEN=
```

- [ ] **Step 9: Run the tests to verify they pass**

```bash
vendor/bin/pest tests/Unit/Services/CaisseCafabClientTest.php tests/Unit/Services/CachetServiceDepenseTest.php
vendor/bin/pest
```

Expected: PASS, full suite green. Every other test that exercises `CachetService::valider()` (Task 3's `CachetValidationTest.php`) still passes unchanged, because the default constructor argument means callers that don't inject a client get a real `CaisseCafabClient` whose HTTP call — with no `services.caisse_cafab.url` configured in the test environment — fails fast and is caught, leaving `depense_erreur` set instead of throwing. Confirm this explicitly by re-running `CachetValidationTest.php` and checking it's still green.

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: create a Caisse CAFAB depense when a cachet is validated, with retry on failure"
```

---

## Task 6: Vue d'ensemble des paiements

**Repository:** `presence-paiement-cafab`

**Files:**
- Create: `app/Http/Controllers/Admin/PaiementController.php`
- Modify: `routes/web.php` (add `admin.paiements.index` inside the existing `admin.` group)
- Create: `resources/views/admin/paiements/index.blade.php`
- Modify: `resources/views/layouts/navigation.blade.php` (add a "Paiements" nav link, desktop + responsive)
- Test: `tests/Feature/Admin/PaiementsOverviewTest.php`

**Interfaces:**
- Consumes: `App\Models\Prestation`, `App\Models\Cachet`, `App\Enums\StatutCachet` (Task 1).
- Produces: route `admin.paiements.index`, query params `date_debut`, `date_fin`, `fille_id` (all optional).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/PaiementsOverviewTest.php`:

```php
<?php

use App\Enums\StatutCachet;
use App\Enums\UserRole;
use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Prestation;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from the payments overview', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.paiements.index'))->assertForbidden();
});

it('shows the total due, paid, and remaining per prestation', function () {
    $prestation = Prestation::factory()->create(['titre' => 'Spectacle', 'date' => '2026-09-10']);
    Cachet::factory()->create(['prestation_id' => $prestation->id, 'montant' => 5000, 'statut' => StatutCachet::ValideePayee]);
    Cachet::factory()->create(['prestation_id' => $prestation->id, 'montant' => 3000, 'statut' => StatutCachet::Du]);

    $response = $this->actingAs($this->admin)->get(route('admin.paiements.index'));

    $response->assertOk();
    $response->assertSee('Spectacle');
    $response->assertSeeText('8000');
    $response->assertSeeText('5000');
    $response->assertSeeText('3000');
});

it('excludes annule cachets from the totals', function () {
    $prestation = Prestation::factory()->create(['titre' => 'Gala']);
    Cachet::factory()->create(['prestation_id' => $prestation->id, 'montant' => 5000, 'statut' => StatutCachet::Annule]);

    $response = $this->actingAs($this->admin)->get(route('admin.paiements.index'));

    $response->assertOk()->assertDontSeeText('5000');
});

it('filters by period', function () {
    Prestation::factory()->create(['titre' => 'Dans la période', 'date' => '2026-09-15']);
    Prestation::factory()->create(['titre' => 'Hors période', 'date' => '2026-01-01']);

    $response = $this->actingAs($this->admin)->get(route('admin.paiements.index', [
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-09-30',
    ]));

    $response->assertSee('Dans la période')->assertDontSee('Hors période');
});

it('filters by fille', function () {
    $fille = Fille::factory()->create();
    $autreFille = Fille::factory()->create();
    $avecFille = Prestation::factory()->create(['titre' => 'Avec la fille']);
    $sansFille = Prestation::factory()->create(['titre' => 'Sans la fille']);
    Cachet::factory()->create(['prestation_id' => $avecFille->id, 'fille_id' => $fille->id]);
    Cachet::factory()->create(['prestation_id' => $sansFille->id, 'fille_id' => $autreFille->id]);

    $response = $this->actingAs($this->admin)->get(route('admin.paiements.index', ['fille_id' => $fille->id]));

    $response->assertSee('Avec la fille')->assertDontSee('Sans la fille');
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/pest tests/Feature/Admin/PaiementsOverviewTest.php
```

Expected: FAIL — `PaiementController` and the route don't exist yet.

- [ ] **Step 3: Create the controller**

`app/Http/Controllers/Admin/PaiementController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutCachet;
use App\Http\Controllers\Controller;
use App\Models\Fille;
use App\Models\Prestation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaiementController extends Controller
{
    public function index(Request $request): View
    {
        $query = Prestation::with(['cachets' => fn ($q) => $q->where('statut', '!=', StatutCachet::Annule)])
            ->orderByDesc('date');

        if ($request->filled('date_debut')) {
            $query->whereDate('date', '>=', $request->date('date_debut'));
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('date', '<=', $request->date('date_fin'));
        }

        if ($request->filled('fille_id')) {
            $query->whereHas('cachets', fn ($q) => $q->where('fille_id', $request->integer('fille_id'))
                ->where('statut', '!=', StatutCachet::Annule));
        }

        $prestations = $query->get()->map(function (Prestation $prestation) {
            $du = $prestation->cachets->sum(fn ($c) => (float) $c->montant);
            $paye = $prestation->cachets->where('statut', StatutCachet::ValideePayee)->sum(fn ($c) => (float) $c->montant);

            return [
                'prestation' => $prestation,
                'total_du' => $du,
                'total_paye' => $paye,
                'reste_a_payer' => $du - $paye,
            ];
        });

        $filles = Fille::orderBy('nom')->get();

        return view('admin.paiements.index', compact('prestations', 'filles'));
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, inside the existing `admin.` group:

```php
use App\Http\Controllers\Admin\PaiementController;

Route::get('paiements', [PaiementController::class, 'index'])->name('paiements.index');
```

- [ ] **Step 5: Create the view**

`resources/views/admin/paiements/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Vue d'ensemble des paiements</h1>
    </x-slot>

    <form method="GET">
        <label for="date_debut">Du</label>
        <input id="date_debut" name="date_debut" type="date" value="{{ request('date_debut') }}">

        <label for="date_fin">Au</label>
        <input id="date_fin" name="date_fin" type="date" value="{{ request('date_fin') }}">

        <label for="fille_id">Fille</label>
        <select id="fille_id" name="fille_id">
            <option value="">Toutes</option>
            @foreach ($filles as $fille)
                <option value="{{ $fille->id }}" {{ (string) request('fille_id') === (string) $fille->id ? 'selected' : '' }}>
                    {{ $fille->prenom }} {{ $fille->nom }}
                </option>
            @endforeach
        </select>

        <button type="submit">Filtrer</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Prestation</th>
                <th>Date</th>
                <th>Montant total dû</th>
                <th>Montant validé payé</th>
                <th>Reste à payer</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($prestations as $ligne)
                <tr>
                    <td>{{ $ligne['prestation']->titre }}</td>
                    <td>{{ $ligne['prestation']->date->format('d/m/Y') }}</td>
                    <td>{{ number_format($ligne['total_du'], 2) }}</td>
                    <td>{{ number_format($ligne['total_paye'], 2) }}</td>
                    <td>{{ number_format($ligne['reste_a_payer'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-app-layout>
```

- [ ] **Step 6: Add the nav link**

In `resources/views/layouts/navigation.blade.php`, inside the desktop admin block, right after the `admin.prestations.*` link added in Task 1:

```blade
<x-nav-link :href="route('admin.paiements.index')" :active="request()->routeIs('admin.paiements.*')">
    {{ __('Paiements') }}
</x-nav-link>
```

And the matching responsive block:

```blade
<x-responsive-nav-link :href="route('admin.paiements.index')" :active="request()->routeIs('admin.paiements.*')">
    {{ __('Paiements') }}
</x-responsive-nav-link>
```

- [ ] **Step 7: Run the tests to verify they pass**

```bash
vendor/bin/pest tests/Feature/Admin/PaiementsOverviewTest.php
vendor/bin/pest
vendor/bin/pint --test
```

Expected: PASS, full suite green, Pint clean.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add admin payments overview, filterable by period and fille"
```

---

## End of plan checklist

- [ ] `presence-paiement-cafab`: `vendor/bin/pest` passes in full.
- [ ] `presence-paiement-cafab`: `vendor/bin/pint --test` reports no issues.
- [ ] `caisse-depenses`: `vendor/bin/pest` passes in full, including the pre-existing `Operations` web-UI tests (unaffected by the new `external_reference` column and the parallel API surface).
- [ ] `caisse-depenses`: `vendor/bin/pint --test` reports no issues.
- [ ] No inline `style="..."` was introduced anywhere in the Blade views written in this plan.
- [ ] An admin can create a prestation, affect several filles with per-fille montant overrides, and see it listed.
- [ ] A fille can, at the kiosk, declare having (or not having) received her cachet — but only after the prestation's date, only for herself, and only while the cachet isn't yet validated or annulled; a Coach identified at the kiosk never sees this option.
- [ ] An admin can validate a cachet (triggering the Caisse CAFAB call) or correct a declaration with a motif — never both at once on a finalised cachet.
- [ ] Cancelling a prestation annule its non-finalised cachets and leaves already-validated ones untouched.
- [ ] `caisse-depenses`' `POST /api/operations` rejects requests with no or wrong service token, creates a `depense` operation on first call, and returns the same operation without duplicating on a repeated `external_reference`.
- [ ] Validating a cachet still sets it `validee_payee` locally even when the Caisse CAFAB call fails, recording the error and offering a retry that never creates a second dépense once the first succeeded.
- [ ] The payments overview shows correct totals due/paid/remaining per prestation and filters by period and by fille.
- [ ] Next increment: **Rapports** (ponctualité et dépenses de prestations, avec export Excel) — do not start it until this plan's checklist above is fully green, and until the two open hypotheses from the note de cadrage (format exact du PIN, fréquence des rapports) are confirmed with CAFAB if they haven't been already.
