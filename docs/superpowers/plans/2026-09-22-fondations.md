# Fondations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the Présence & Paiements CAFAB Laravel project with authentication (Admin/Coach), the registre (roster) of coachs and filles, and the security baseline — the foundation every later plan (planning & pointage, prestations & paiements, intégration Caisse CAFAB, rapports) builds on.

**Architecture:** A new Laravel 12 / PHP 8.3+ project, independent from Caisse CAFAB, following the same conventions (Blade + Vite, no CDN assets, Pest tests, Pint formatting, role-based access via a custom middleware, accounts created only via an artisan command). Two roster tables (`coaches`, `filles`) hold the people who use the kiosk; `coaches` links to a `users` login account, `filles` never log in and are identified only by a PIN.

**Tech Stack:** Laravel 12, PHP 8.3+, Blade, Vite, SQLite (dev/test) / MySQL (prod), Laravel Breeze (blade stack, customized), Pest, Laravel Pint.

**Spec:** `C:\wamp64\www\caisse_cafab\documentations\presence-paiement-cafab\` — `technum_note-cadrage_presence-paiement-cafab_20260922.docx`, `technum_specifications-fonctionnelles_presence-paiement-cafab_20260922.docx`, `technum_regles-metier_presence-paiement-cafab_20260922.docx` (source Markdown consulted for this plan: sections 1 "Registre des coachs et des filles" of the spec, sections 1 "Rôles et permissions" and 4 "Registre des personnes" of the règles métier). Design validated against the Claude Design mockup at `https://claude.ai/artifact/Tcnj5Z4GyfFQkJDzu5fMiy`.

## Global Constraints

- PHP 8.3+, Laravel 12 (matches Caisse CAFAB, same developer maintains both).
- No CDN resources (fonts, styles, scripts) — everything self-hosted via npm/Vite, same rule as Caisse CAFAB's `SecurityHeaders` CSP.
- No inline `style="..."` in Blade views (CSP `style-src 'self'` will forbid it once Task 8 lands — write views without inline styles from the start to avoid rework).
- Accounts (Admin, Coach) are created only via an artisan command — no public self-registration route.
- Filles never get a login account — identification is PIN-only, always at the kiosk (no admin/coach screen "as" a fille).
- PIN codes are 4 digits, globally unique across `coaches` and `filles` combined (a kiosk lookup by PIN must resolve to exactly one person).
- Désactivation of a coach or fille is a `statut` flag (`actif`/`inactif`), never a delete — history must stay intact for later plans (pointage, paiements).

---

## Task 1: Scaffold the Laravel project and tooling

**Files:**
- Create: the whole default Laravel skeleton under `C:\wamp64\www\caisse_cafab\presence-paiement-cafab\`
- Create: `.env`, `.env.example`
- Modify: `.gitignore` (add `/database/database.sqlite`)

**Interfaces:**
- Produces: a running `php artisan serve` app, `php artisan test` green, `vendor/bin/pint --test` clean — the base every later task builds on.

- [ ] **Step 1: Scaffold Laravel into a temp folder, then move it into the already-initialized repo**

The repo directory already contains `.git/` and `docs/` (created ahead of this plan). `composer create-project` refuses a non-empty target, so scaffold into a throwaway sibling folder and move the files in.

```bash
cd "C:\wamp64\www\caisse_cafab"
composer create-project laravel/laravel presence-paiement-cafab-tmp "^12.0"
cd presence-paiement-cafab-tmp
shopt -s dotglob
mv * ../presence-paiement-cafab/
cd ..
rmdir presence-paiement-cafab-tmp
cd presence-paiement-cafab
```

- [ ] **Step 2: Configure the environment**

Edit `.env` (and mirror the relevant lines into `.env.example`, stripped of real secrets):

```
APP_NAME="Présence & Paiements CAFAB"
APP_TIMEZONE=Africa/Porto-Novo
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr

DB_CONNECTION=sqlite
# Local dev (WAMP) : laisser sqlite ; production : décommenter le bloc MySQL ci-dessous
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=presence_cafab
# DB_USERNAME=
# DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
MAIL_MAILER=log
```

```bash
touch database/database.sqlite
```

- [ ] **Step 3: Install Breeze (Blade stack) and the dev tooling**

```bash
composer require laravel/breeze --dev
php artisan breeze:install blade --no-interaction
composer require --dev laravel/pint pestphp/pest pestphp/pest-plugin-laravel
php artisan pest:install --no-interaction
npm install
npm run build
```

- [ ] **Step 4: Run migrations and verify the baseline**

```bash
php artisan migrate
php artisan test
vendor/bin/pint --test
```

Expected: migrations run clean, the Breeze-generated Pest suite passes, Pint reports no style issues.

- [ ] **Step 5: Commit the scaffold**

```bash
git add -A
git commit -m "chore: scaffold Présence & Paiements CAFAB Laravel project"
```

- [ ] **Step 6: Add this plan to the repo**

```bash
cp "C:\wamp64\www\caisse_cafab\presence-paiement-cafab\docs\superpowers\plans\2026-09-22-fondations.md" docs/superpowers/plans/2026-09-22-fondations.md
git add docs/superpowers/plans/2026-09-22-fondations.md
git commit -m "docs: add fondations implementation plan"
```

(The `cp` is a no-op if the file was already written straight into the repo — verify with `git status` before committing; skip the copy if there is nothing to stage.)

---

## Task 2: Roles, role middleware, and account creation

**Files:**
- Create: `app/Enums/UserRole.php`
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php` (add `role`)
- Create: `app/Http/Middleware/EnsureUserHasRole.php`
- Modify: `bootstrap/app.php` (register the `role` middleware alias, remove/guard the registration route)
- Modify: `routes/auth.php` (drop the public registration route — Breeze generates one by default)
- Create: `app/Console/Commands/CreateUserCommand.php`
- Test: `tests/Feature/Auth/RoleMiddlewareTest.php`
- Test: `tests/Feature/Console/CreateUserCommandTest.php`

**Interfaces:**
- Produces: `UserRole::Admin`, `UserRole::Coach` (backed string enum), middleware alias `role:admin` / `role:coach`, artisan command `users:create {name} {email} {--role=coach}`.
- Consumes: nothing from earlier tasks beyond the scaffold.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Auth/RoleMiddlewareTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;

it('lets an admin reach an admin-only route', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->get('/admin/ping');

    $response->assertOk();
});

it('blocks a coach from an admin-only route', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $response = $this->actingAs($coach)->get('/admin/ping');

    $response->assertForbidden();
});

it('blocks a guest from an admin-only route', function () {
    $response = $this->get('/admin/ping');

    $response->assertRedirect('/login');
});
```

Add a throwaway route for this test only, in `routes/web.php`, right after the existing routes:

```php
Route::middleware(['auth', 'role:admin'])->get('/admin/ping', fn () => 'pong');
```

`tests/Feature/Console/CreateUserCommandTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;

it('creates a coach account with a random password', function () {
    $this->artisan('users:create', [
        'name' => 'Prudence Aïvodji',
        'email' => 'prudence@cafab.bj',
        '--role' => 'coach',
    ])->assertSuccessful();

    $user = User::where('email', 'prudence@cafab.bj')->firstOrFail();

    expect($user->name)->toBe('Prudence Aïvodji');
    expect($user->role)->toBe(UserRole::Coach);
});

it('rejects an invalid role', function () {
    $this->artisan('users:create', [
        'name' => 'Test',
        'email' => 'test@cafab.bj',
        '--role' => 'invalide',
    ])->assertFailed();

    expect(User::where('email', 'test@cafab.bj')->exists())->toBeFalse();
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'existing@cafab.bj']);

    $this->artisan('users:create', [
        'name' => 'Autre',
        'email' => 'existing@cafab.bj',
        '--role' => 'admin',
    ])->assertFailed();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/pest tests/Feature/Auth/RoleMiddlewareTest.php tests/Feature/Console/CreateUserCommandTest.php
```

Expected: FAIL — `role` middleware alias and `users:create` command do not exist yet, `role` column and `UserRole` enum do not exist yet.

- [ ] **Step 3: Create the `UserRole` enum**

`app/Enums/UserRole.php`:

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Coach = 'coach';
}
```

- [ ] **Step 4: Add `role` to the users migration and cast it on the model**

Edit `database/migrations/0001_01_01_000000_create_users_table.php`, inside the `up()` method's `Schema::create('users', ...)` block, add after the `email_verified_at` line:

```php
$table->string('role', 20)->default('coach');
```

`app/Models/User.php` — add the cast and fillable entry:

```php
use App\Enums\UserRole;

// inside the class:
protected $fillable = [
    'name',
    'email',
    'password',
    'role',
];

protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
    ];
}
```

Update `database/factories/UserFactory.php` so the default factory state sets a role:

```php
use App\Enums\UserRole;

// inside definition():
'role' => UserRole::Coach,
```

- [ ] **Step 5: Create the role middleware and register it**

`app/Http/Middleware/EnsureUserHasRole.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_if($request->user()?->role?->value !== $role, 403);

        return $next($request);
    }
}
```

In `bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware) { ... })`, add:

```php
$middleware->alias([
    'role' => \App\Http\Middleware\EnsureUserHasRole::class,
]);
```

- [ ] **Step 6: Remove public self-registration**

`routes/auth.php` — delete (or comment out, matching Caisse CAFAB's convention for the disabled password-reset routes) the `Route::get('register', ...)` and `Route::post('register', ...)` lines, and the `RegisteredUserController` import if it becomes unused.

- [ ] **Step 7: Create the `users:create` artisan command**

`app/Console/Commands/CreateUserCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateUserCommand extends Command
{
    protected $signature = 'users:create {name} {email} {--role=coach}';

    protected $description = 'Crée un compte Admin ou Coach avec un mot de passe généré';

    public function handle(): int
    {
        $data = [
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'role' => $this->option('role'),
        ];

        try {
            validator($data, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
                'role' => ['required', Rule::enum(UserRole::class)],
            ])->validate();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->error("{$field} : ".implode(' ', $messages));
            }

            return self::FAILURE;
        }

        $password = Str::password(16);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => UserRole::from($data['role']),
            'password' => Hash::make($password),
        ]);

        $this->warn("Compte créé. Mot de passe (à noter, affiché une seule fois) : {$password}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 8: Run the tests to verify they pass**

```bash
php artisan migrate:fresh
vendor/bin/pest tests/Feature/Auth/RoleMiddlewareTest.php tests/Feature/Console/CreateUserCommandTest.php
```

Expected: PASS.

- [ ] **Step 9: Keep the smoke route, confirm the full suite**

Leave `Route::middleware(['auth', 'role:admin'])->get('/admin/ping', fn () => 'pong');` in `routes/web.php` permanently — it is a tiny, always-on smoke route for the role middleware itself, independent of whatever real `admin.*` routes later tasks add. Keep the three tests from Step 1 in `tests/Feature/Auth/RoleMiddlewareTest.php` unchanged.

```bash
vendor/bin/pest
```

Expected: PASS, full suite green.

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add user roles, role middleware, and users:create command"
```

---

## Task 3: Coach and Fille roster schema

**Files:**
- Create: `app/Enums/StatutPersonne.php`
- Create: `database/migrations/xxxx_xx_xx_xxxxxx_create_coaches_table.php`
- Create: `database/migrations/xxxx_xx_xx_xxxxxx_create_filles_table.php`
- Create: `app/Models/Coach.php`
- Create: `app/Models/Fille.php`
- Create: `database/factories/CoachFactory.php`
- Create: `database/factories/FilleFactory.php`
- Test: `tests/Unit/Models/CoachTest.php`
- Test: `tests/Unit/Models/FilleTest.php`

**Interfaces:**
- Consumes: `App\Enums\UserRole` (Task 2), `App\Models\User` (Task 2).
- Produces: `Coach` (fields: `user_id`, `pin`, `contact`, `statut`, `date_entree`), `Fille` (fields: `nom`, `prenom`, `contact`, `pin`, `statut`, `date_entree`), both with `statut` cast to `StatutPersonne`. `Coach::user(): BelongsTo`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Models/CoachTest.php`:

```php
<?php

use App\Enums\StatutPersonne;
use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\User;

it('belongs to a user account', function () {
    $user = User::factory()->create(['role' => UserRole::Coach]);
    $coach = Coach::factory()->create(['user_id' => $user->id]);

    expect($coach->user->is($user))->toBeTrue();
});

it('casts statut to the StatutPersonne enum and defaults to actif', function () {
    $coach = Coach::factory()->create();

    expect($coach->statut)->toBe(StatutPersonne::Actif);
});

it('rejects a duplicate pin', function () {
    Coach::factory()->create(['pin' => '1234']);

    Coach::factory()->create(['pin' => '1234']);
})->throws(\Illuminate\Database\QueryException::class);
```

`tests/Unit/Models/FilleTest.php`:

```php
<?php

use App\Enums\StatutPersonne;
use App\Models\Fille;

it('casts statut to the StatutPersonne enum and defaults to actif', function () {
    $fille = Fille::factory()->create();

    expect($fille->statut)->toBe(StatutPersonne::Actif);
});

it('rejects a duplicate pin', function () {
    Fille::factory()->create(['pin' => '4321']);

    Fille::factory()->create(['pin' => '4321']);
})->throws(\Illuminate\Database\QueryException::class);

it('exposes nom and prenom', function () {
    $fille = Fille::factory()->create(['nom' => 'Hounkpatin', 'prenom' => 'Sènami']);

    expect($fille->nom)->toBe('Hounkpatin');
    expect($fille->prenom)->toBe('Sènami');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/pest tests/Unit/Models/CoachTest.php tests/Unit/Models/FilleTest.php
```

Expected: FAIL — `Coach`, `Fille`, factories, and tables do not exist yet.

- [ ] **Step 3: Create the `StatutPersonne` enum**

`app/Enums/StatutPersonne.php`:

```php
<?php

namespace App\Enums;

enum StatutPersonne: string
{
    case Actif = 'actif';
    case Inactif = 'inactif';
}
```

- [ ] **Step 4: Create the migrations**

```bash
php artisan make:migration create_coaches_table
php artisan make:migration create_filles_table
```

`database/migrations/xxxx_xx_xx_xxxxxx_create_coaches_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pin', 4)->unique();
            $table->string('contact')->nullable();
            $table->string('statut', 20)->default('actif');
            $table->date('date_entree');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaches');
    }
};
```

`database/migrations/xxxx_xx_xx_xxxxxx_create_filles_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filles', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->string('contact')->nullable();
            $table->string('pin', 4)->unique();
            $table->string('statut', 20)->default('actif');
            $table->date('date_entree');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filles');
    }
};
```

- [ ] **Step 5: Create the models**

`app/Models/Coach.php`:

```php
<?php

namespace App\Models;

use App\Enums\StatutPersonne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coach extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'pin', 'contact', 'statut', 'date_entree'];

    protected function casts(): array
    {
        return [
            'statut' => StatutPersonne::class,
            'date_entree' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

`app/Models/Fille.php`:

```php
<?php

namespace App\Models;

use App\Enums\StatutPersonne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fille extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'prenom', 'contact', 'pin', 'statut', 'date_entree'];

    protected function casts(): array
    {
        return [
            'statut' => StatutPersonne::class,
            'date_entree' => 'date',
        ];
    }
}
```

- [ ] **Step 6: Create the factories**

`database/factories/CoachFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CoachFactory extends Factory
{
    protected $model = Coach::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => UserRole::Coach]),
            'pin' => $this->faker->unique()->numerify('####'),
            'contact' => $this->faker->optional()->phoneNumber(),
            'statut' => 'actif',
            'date_entree' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
        ];
    }
}
```

`database/factories/FilleFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Fille;
use Illuminate\Database\Eloquent\Factories\Factory;

class FilleFactory extends Factory
{
    protected $model = Fille::class;

    public function definition(): array
    {
        return [
            'nom' => $this->faker->lastName(),
            'prenom' => $this->faker->firstName('female'),
            'contact' => $this->faker->optional()->phoneNumber(),
            'pin' => $this->faker->unique()->numerify('####'),
            'statut' => 'actif',
            'date_entree' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
        ];
    }
}
```

- [ ] **Step 7: Run migrations and the tests to verify they pass**

```bash
php artisan migrate
vendor/bin/pest tests/Unit/Models/CoachTest.php tests/Unit/Models/FilleTest.php
```

Expected: PASS.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add coach and fille roster schema"
```

---

## Task 4: Cross-table unique PIN generator

**Files:**
- Create: `app/Services/PinGenerator.php`
- Test: `tests/Unit/Services/PinGeneratorTest.php`

**Interfaces:**
- Consumes: `App\Models\Coach`, `App\Models\Fille` (Task 3).
- Produces: `PinGenerator::generate(): string` (4-digit numeric string, unique across `coaches` and `filles`), `PinGenerator::isTaken(string $pin): bool`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Services/PinGeneratorTest.php`:

```php
<?php

use App\Models\Coach;
use App\Models\Fille;
use App\Services\PinGenerator;

it('generates a 4-digit numeric pin', function () {
    $pin = (new PinGenerator())->generate();

    expect($pin)->toMatch('/^\d{4}$/');
});

it('detects a pin already used by a fille', function () {
    Fille::factory()->create(['pin' => '1111']);

    expect((new PinGenerator())->isTaken('1111'))->toBeTrue();
    expect((new PinGenerator())->isTaken('2222'))->toBeFalse();
});

it('detects a pin already used by a coach', function () {
    Coach::factory()->create(['pin' => '3333']);

    expect((new PinGenerator())->isTaken('3333'))->toBeTrue();
});

it('never generates a pin already taken by a fille or a coach', function () {
    Fille::factory()->create(['pin' => '5555']);
    Coach::factory()->create(['pin' => '6666']);

    $generator = new PinGenerator();

    for ($i = 0; $i < 200; $i++) {
        $pin = $generator->generate();
        expect($pin)->not->toBe('5555');
        expect($pin)->not->toBe('6666');
    }
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/pest tests/Unit/Services/PinGeneratorTest.php
```

Expected: FAIL — `App\Services\PinGenerator` does not exist yet.

- [ ] **Step 3: Implement the service**

`app/Services/PinGenerator.php`:

```php
<?php

namespace App\Services;

use App\Models\Coach;
use App\Models\Fille;

class PinGenerator
{
    public function generate(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while ($this->isTaken($pin));

        return $pin;
    }

    public function isTaken(string $pin): bool
    {
        return Coach::where('pin', $pin)->exists() || Fille::where('pin', $pin)->exists();
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
vendor/bin/pest tests/Unit/Services/PinGeneratorTest.php
```

Expected: PASS. Note: the "never generates a taken pin" test calls `generate()` 200 times against a 10 000-code space with only 2 codes excluded — this is deterministic in practice (the loop in `generate()` cannot return an excluded code by construction) but exercises the real collision path rather than trusting it blindly.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add cross-table unique PIN generator"
```

---

## Task 5: Registre — gestion des coachs (Admin)

**Files:**
- Create: `app/Http/Controllers/Admin/CoachController.php`
- Create: `app/Http/Requests/Admin/StoreCoachRequest.php`
- Create: `app/Http/Requests/Admin/UpdateCoachRequest.php`
- Modify: `routes/web.php` (add the `admin.coaches.*` group)
- Create: `resources/views/admin/coaches/index.blade.php`
- Create: `resources/views/admin/coaches/create.blade.php`
- Create: `resources/views/admin/coaches/edit.blade.php`
- Test: `tests/Feature/Admin/CoachManagementTest.php`

**Interfaces:**
- Consumes: `App\Models\Coach`, `App\Enums\StatutPersonne` (Task 3), `App\Services\PinGenerator` (Task 4), `App\Enums\UserRole` (Task 2).
- Produces: routes `admin.coaches.index`, `admin.coaches.create`, `admin.coaches.store`, `admin.coaches.edit`, `admin.coaches.update`, `admin.coaches.toggle-statut`, `admin.coaches.regenerate-pin`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Admin/CoachManagementTest.php`:

```php
<?php

use App\Enums\StatutPersonne;
use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from the registre', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.coaches.index'))->assertForbidden();
});

it('lets the admin list coaches', function () {
    Coach::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.coaches.index'))
        ->assertOk();
});

it('lets the admin create a coach with a generated pin', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.coaches.store'), [
        'name' => 'Prudence Aïvodji',
        'email' => 'prudence@cafab.bj',
        'contact' => '+229 01 00 00 00 00',
        'date_entree' => '2026-01-15',
    ]);

    $response->assertRedirect(route('admin.coaches.index'));

    $user = User::where('email', 'prudence@cafab.bj')->firstOrFail();
    expect($user->role)->toBe(UserRole::Coach);

    $coach = Coach::where('user_id', $user->id)->firstOrFail();
    expect($coach->pin)->toMatch('/^\d{4}$/');
    expect($coach->statut)->toBe(StatutPersonne::Actif);
});

it('lets the admin update a coach contact', function () {
    $coach = Coach::factory()->create(['contact' => '+229 00 00 00 00 00']);

    $this->actingAs($this->admin)
        ->put(route('admin.coaches.update', $coach), [
            'contact' => '+229 11 11 11 11 11',
            'date_entree' => $coach->date_entree->format('Y-m-d'),
        ])
        ->assertRedirect(route('admin.coaches.index'));

    expect($coach->fresh()->contact)->toBe('+229 11 11 11 11 11');
});

it('lets the admin deactivate then reactivate a coach', function () {
    $coach = Coach::factory()->create(['statut' => 'actif']);

    $this->actingAs($this->admin)->patch(route('admin.coaches.toggle-statut', $coach));
    expect($coach->fresh()->statut)->toBe(StatutPersonne::Inactif);

    $this->actingAs($this->admin)->patch(route('admin.coaches.toggle-statut', $coach));
    expect($coach->fresh()->statut)->toBe(StatutPersonne::Actif);
});

it('lets the admin regenerate a coach pin', function () {
    $coach = Coach::factory()->create(['pin' => '9999']);

    $this->actingAs($this->admin)->patch(route('admin.coaches.regenerate-pin', $coach));

    expect($coach->fresh()->pin)->not->toBe('9999');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/pest tests/Feature/Admin/CoachManagementTest.php
```

Expected: FAIL — routes and controller do not exist yet.

- [ ] **Step 3: Create the form requests**

`app/Http/Requests/Admin/StoreCoachRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'contact' => ['nullable', 'string', 'max:50'],
            'date_entree' => ['required', 'date'],
        ];
    }
}
```

`app/Http/Requests/Admin/UpdateCoachRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact' => ['nullable', 'string', 'max:50'],
            'date_entree' => ['required', 'date'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/Admin/CoachController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutPersonne;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCoachRequest;
use App\Http\Requests\Admin\UpdateCoachRequest;
use App\Models\Coach;
use App\Models\User;
use App\Services\PinGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CoachController extends Controller
{
    public function index(): View
    {
        $coaches = Coach::with('user')->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.coaches.index', compact('coaches'));
    }

    public function create(): View
    {
        return view('admin.coaches.create');
    }

    public function store(StoreCoachRequest $request, PinGenerator $pinGenerator): RedirectResponse
    {
        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'role' => UserRole::Coach,
            'password' => Hash::make(Str::password(16)),
        ]);

        Coach::create([
            'user_id' => $user->id,
            'pin' => $pinGenerator->generate(),
            'contact' => $request->string('contact')->value() ?: null,
            'date_entree' => $request->date('date_entree'),
        ]);

        return redirect()->route('admin.coaches.index')->with('message', 'Coach ajouté.');
    }

    public function edit(Coach $coach): View
    {
        return view('admin.coaches.edit', compact('coach'));
    }

    public function update(UpdateCoachRequest $request, Coach $coach): RedirectResponse
    {
        $coach->update([
            'contact' => $request->string('contact')->value() ?: null,
            'date_entree' => $request->date('date_entree'),
        ]);

        return redirect()->route('admin.coaches.index')->with('message', 'Coach mis à jour.');
    }

    public function toggleStatut(Coach $coach): RedirectResponse
    {
        $coach->update([
            'statut' => $coach->statut === StatutPersonne::Actif
                ? StatutPersonne::Inactif
                : StatutPersonne::Actif,
        ]);

        return redirect()->route('admin.coaches.index')->with('message', 'Statut mis à jour.');
    }

    public function regeneratePin(Coach $coach, PinGenerator $pinGenerator): RedirectResponse
    {
        $coach->update(['pin' => $pinGenerator->generate()]);

        return redirect()->route('admin.coaches.index')->with('message', 'Nouveau code PIN généré.');
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, add (near the other route groups):

```php
use App\Http\Controllers\Admin\CoachController;

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('coaches', CoachController::class)->except(['show', 'destroy']);
    Route::patch('coaches/{coach}/toggle-statut', [CoachController::class, 'toggleStatut'])
        ->name('coaches.toggle-statut');
    Route::patch('coaches/{coach}/regenerate-pin', [CoachController::class, 'regeneratePin'])
        ->name('coaches.regenerate-pin');
});
```

- [ ] **Step 6: Create the views**

`resources/views/admin/coaches/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Registre des coachs</h1>
    </x-slot>

    <a href="{{ route('admin.coaches.create') }}">Ajouter un coach</a>

    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Email</th>
                <th>Contact</th>
                <th>PIN</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($coaches as $coach)
                <tr>
                    <td>{{ $coach->user->name }}</td>
                    <td>{{ $coach->user->email }}</td>
                    <td>{{ $coach->contact ?? '—' }}</td>
                    <td>{{ $coach->pin }}</td>
                    <td>{{ $coach->statut->value }}</td>
                    <td>
                        <a href="{{ route('admin.coaches.edit', $coach) }}">Modifier</a>
                        <form action="{{ route('admin.coaches.toggle-statut', $coach) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit">
                                {{ $coach->statut->value === 'actif' ? 'Désactiver' : 'Réactiver' }}
                            </button>
                        </form>
                        <form action="{{ route('admin.coaches.regenerate-pin', $coach) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit">Régénérer le PIN</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $coaches->links() }}
</x-app-layout>
```

`resources/views/admin/coaches/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Ajouter un coach</h1>
    </x-slot>

    <form action="{{ route('admin.coaches.store') }}" method="POST">
        @csrf

        <label for="name">Nom complet</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" required>
        @error('name') <p>{{ $message }}</p> @enderror

        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required>
        @error('email') <p>{{ $message }}</p> @enderror

        <label for="contact">Contact</label>
        <input id="contact" name="contact" type="text" value="{{ old('contact') }}">

        <label for="date_entree">Date d'entrée</label>
        <input id="date_entree" name="date_entree" type="date" value="{{ old('date_entree') }}" required>
        @error('date_entree') <p>{{ $message }}</p> @enderror

        <button type="submit">Créer</button>
    </form>
</x-app-layout>
```

`resources/views/admin/coaches/edit.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Modifier {{ $coach->user->name }}</h1>
    </x-slot>

    <form action="{{ route('admin.coaches.update', $coach) }}" method="POST">
        @csrf
        @method('PUT')

        <label for="contact">Contact</label>
        <input id="contact" name="contact" type="text" value="{{ old('contact', $coach->contact) }}">

        <label for="date_entree">Date d'entrée</label>
        <input id="date_entree" name="date_entree" type="date"
               value="{{ old('date_entree', $coach->date_entree->format('Y-m-d')) }}" required>
        @error('date_entree') <p>{{ $message }}</p> @enderror

        <button type="submit">Enregistrer</button>
    </form>
</x-app-layout>
```

- [ ] **Step 7: Run the tests to verify they pass**

```bash
vendor/bin/pest tests/Feature/Admin/CoachManagementTest.php
```

Expected: PASS.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add coach registre management (admin)"
```

---

## Task 6: Registre — gestion des filles (Admin)

**Files:**
- Create: `app/Http/Controllers/Admin/FilleController.php`
- Create: `app/Http/Requests/Admin/StoreFilleRequest.php`
- Create: `app/Http/Requests/Admin/UpdateFilleRequest.php`
- Modify: `routes/web.php` (add the `admin.filles.*` group)
- Create: `resources/views/admin/filles/index.blade.php`
- Create: `resources/views/admin/filles/create.blade.php`
- Create: `resources/views/admin/filles/edit.blade.php`
- Test: `tests/Feature/Admin/FilleManagementTest.php`

**Interfaces:**
- Consumes: `App\Models\Fille`, `App\Enums\StatutPersonne` (Task 3), `App\Services\PinGenerator` (Task 4).
- Produces: routes `admin.filles.index`, `admin.filles.create`, `admin.filles.store`, `admin.filles.edit`, `admin.filles.update`, `admin.filles.toggle-statut`, `admin.filles.regenerate-pin`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Admin/FilleManagementTest.php`:

```php
<?php

use App\Enums\StatutPersonne;
use App\Enums\UserRole;
use App\Models\Fille;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from the registre des filles', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.filles.index'))->assertForbidden();
});

it('lets the admin list filles', function () {
    Fille::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.filles.index'))
        ->assertOk();
});

it('lets the admin create a fille with a generated pin', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.filles.store'), [
        'nom' => 'Hounkpatin',
        'prenom' => 'Sènami',
        'contact' => '+229 01 00 00 00 00',
        'date_entree' => '2026-01-15',
    ]);

    $response->assertRedirect(route('admin.filles.index'));

    $fille = Fille::where('nom', 'Hounkpatin')->where('prenom', 'Sènami')->firstOrFail();
    expect($fille->pin)->toMatch('/^\d{4}$/');
    expect($fille->statut)->toBe(StatutPersonne::Actif);
});

it('lets the admin update a fille contact', function () {
    $fille = Fille::factory()->create(['contact' => '+229 00 00 00 00 00']);

    $this->actingAs($this->admin)
        ->put(route('admin.filles.update', $fille), [
            'nom' => $fille->nom,
            'prenom' => $fille->prenom,
            'contact' => '+229 11 11 11 11 11',
            'date_entree' => $fille->date_entree->format('Y-m-d'),
        ])
        ->assertRedirect(route('admin.filles.index'));

    expect($fille->fresh()->contact)->toBe('+229 11 11 11 11 11');
});

it('lets the admin deactivate then reactivate a fille', function () {
    $fille = Fille::factory()->create(['statut' => 'actif']);

    $this->actingAs($this->admin)->patch(route('admin.filles.toggle-statut', $fille));
    expect($fille->fresh()->statut)->toBe(StatutPersonne::Inactif);

    $this->actingAs($this->admin)->patch(route('admin.filles.toggle-statut', $fille));
    expect($fille->fresh()->statut)->toBe(StatutPersonne::Actif);
});

it('lets the admin regenerate a fille pin', function () {
    $fille = Fille::factory()->create(['pin' => '9999']);

    $this->actingAs($this->admin)->patch(route('admin.filles.regenerate-pin', $fille));

    expect($fille->fresh()->pin)->not->toBe('9999');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/pest tests/Feature/Admin/FilleManagementTest.php
```

Expected: FAIL — routes and controller do not exist yet.

- [ ] **Step 3: Create the form requests**

`app/Http/Requests/Admin/StoreFilleRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreFilleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:50'],
            'date_entree' => ['required', 'date'],
        ];
    }
}
```

`app/Http/Requests/Admin/UpdateFilleRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFilleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:50'],
            'date_entree' => ['required', 'date'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/Admin/FilleController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutPersonne;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFilleRequest;
use App\Http\Requests\Admin\UpdateFilleRequest;
use App\Models\Fille;
use App\Services\PinGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FilleController extends Controller
{
    public function index(): View
    {
        $filles = Fille::orderBy('nom')->paginate(20);

        return view('admin.filles.index', compact('filles'));
    }

    public function create(): View
    {
        return view('admin.filles.create');
    }

    public function store(StoreFilleRequest $request, PinGenerator $pinGenerator): RedirectResponse
    {
        Fille::create([
            'nom' => $request->string('nom'),
            'prenom' => $request->string('prenom'),
            'contact' => $request->string('contact')->value() ?: null,
            'pin' => $pinGenerator->generate(),
            'date_entree' => $request->date('date_entree'),
        ]);

        return redirect()->route('admin.filles.index')->with('message', 'Fille ajoutée.');
    }

    public function edit(Fille $fille): View
    {
        return view('admin.filles.edit', compact('fille'));
    }

    public function update(UpdateFilleRequest $request, Fille $fille): RedirectResponse
    {
        $fille->update([
            'nom' => $request->string('nom'),
            'prenom' => $request->string('prenom'),
            'contact' => $request->string('contact')->value() ?: null,
            'date_entree' => $request->date('date_entree'),
        ]);

        return redirect()->route('admin.filles.index')->with('message', 'Fiche mise à jour.');
    }

    public function toggleStatut(Fille $fille): RedirectResponse
    {
        $fille->update([
            'statut' => $fille->statut === StatutPersonne::Actif
                ? StatutPersonne::Inactif
                : StatutPersonne::Actif,
        ]);

        return redirect()->route('admin.filles.index')->with('message', 'Statut mis à jour.');
    }

    public function regeneratePin(Fille $fille, PinGenerator $pinGenerator): RedirectResponse
    {
        $fille->update(['pin' => $pinGenerator->generate()]);

        return redirect()->route('admin.filles.index')->with('message', 'Nouveau code PIN généré.');
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, inside the same `admin.` route group created in Task 5:

```php
use App\Http\Controllers\Admin\FilleController;

Route::resource('filles', FilleController::class)->except(['show', 'destroy']);
Route::patch('filles/{fille}/toggle-statut', [FilleController::class, 'toggleStatut'])
    ->name('filles.toggle-statut');
Route::patch('filles/{fille}/regenerate-pin', [FilleController::class, 'regeneratePin'])
    ->name('filles.regenerate-pin');
```

- [ ] **Step 6: Create the views**

`resources/views/admin/filles/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Registre des filles</h1>
    </x-slot>

    <a href="{{ route('admin.filles.create') }}">Ajouter une fille</a>
    <a href="{{ route('admin.filles.import') }}">Importer depuis Excel</a>

    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Contact</th>
                <th>PIN</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filles as $fille)
                <tr>
                    <td>{{ $fille->nom }}</td>
                    <td>{{ $fille->prenom }}</td>
                    <td>{{ $fille->contact ?? '—' }}</td>
                    <td>{{ $fille->pin }}</td>
                    <td>{{ $fille->statut->value }}</td>
                    <td>
                        <a href="{{ route('admin.filles.edit', $fille) }}">Modifier</a>
                        <form action="{{ route('admin.filles.toggle-statut', $fille) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit">
                                {{ $fille->statut->value === 'actif' ? 'Désactiver' : 'Réactiver' }}
                            </button>
                        </form>
                        <form action="{{ route('admin.filles.regenerate-pin', $fille) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit">Régénérer le PIN</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $filles->links() }}
</x-app-layout>
```

(The `admin.filles.import` link is wired up in Task 7 — leave it in place now, Task 7 adds the matching route.)

`resources/views/admin/filles/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Ajouter une fille</h1>
    </x-slot>

    <form action="{{ route('admin.filles.store') }}" method="POST">
        @csrf

        <label for="nom">Nom</label>
        <input id="nom" name="nom" type="text" value="{{ old('nom') }}" required>
        @error('nom') <p>{{ $message }}</p> @enderror

        <label for="prenom">Prénom</label>
        <input id="prenom" name="prenom" type="text" value="{{ old('prenom') }}" required>
        @error('prenom') <p>{{ $message }}</p> @enderror

        <label for="contact">Contact</label>
        <input id="contact" name="contact" type="text" value="{{ old('contact') }}">

        <label for="date_entree">Date d'entrée</label>
        <input id="date_entree" name="date_entree" type="date" value="{{ old('date_entree') }}" required>
        @error('date_entree') <p>{{ $message }}</p> @enderror

        <button type="submit">Créer</button>
    </form>
</x-app-layout>
```

`resources/views/admin/filles/edit.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Modifier {{ $fille->prenom }} {{ $fille->nom }}</h1>
    </x-slot>

    <form action="{{ route('admin.filles.update', $fille) }}" method="POST">
        @csrf
        @method('PUT')

        <label for="nom">Nom</label>
        <input id="nom" name="nom" type="text" value="{{ old('nom', $fille->nom) }}" required>
        @error('nom') <p>{{ $message }}</p> @enderror

        <label for="prenom">Prénom</label>
        <input id="prenom" name="prenom" type="text" value="{{ old('prenom', $fille->prenom) }}" required>
        @error('prenom') <p>{{ $message }}</p> @enderror

        <label for="contact">Contact</label>
        <input id="contact" name="contact" type="text" value="{{ old('contact', $fille->contact) }}">

        <label for="date_entree">Date d'entrée</label>
        <input id="date_entree" name="date_entree" type="date"
               value="{{ old('date_entree', $fille->date_entree->format('Y-m-d')) }}" required>
        @error('date_entree') <p>{{ $message }}</p> @enderror

        <button type="submit">Enregistrer</button>
    </form>
</x-app-layout>
```

- [ ] **Step 7: Run the tests to verify they pass**

```bash
vendor/bin/pest tests/Feature/Admin/FilleManagementTest.php
```

Expected: PASS.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add fille registre management (admin)"
```

---

## Task 7: Import Excel des filles (aperçu puis confirmation)

**Files:**
- Create: `app/Imports/FillesPreviewImport.php`
- Create: `app/Http/Controllers/Admin/FilleImportController.php`
- Modify: `routes/web.php` (add `admin.filles.import*` routes)
- Create: `resources/views/admin/filles/import.blade.php`
- Create: `resources/views/admin/filles/import-preview.blade.php`
- Test: `tests/Feature/Admin/FilleImportTest.php`
- Test fixture: `tests/Fixtures/filles-import.xlsx` (created by the test itself via `maatwebsite/excel`'s writer — no binary fixture checked into the repo)

**Interfaces:**
- Consumes: `App\Models\Fille`, `App\Services\PinGenerator` (Task 4/6).
- Produces: routes `admin.filles.import` (GET form), `admin.filles.import.preview` (POST, stages rows in the session and shows a preview with duplicate warnings), `admin.filles.import.confirm` (POST, persists the staged rows).

Composer package `maatwebsite/excel` is required for this task; add it in Step 1.

- [ ] **Step 1: Install the Excel package**

```bash
composer require maatwebsite/excel
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

- [ ] **Step 2: Write the failing test**

`tests/Feature/Admin/FilleImportTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\Fille;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

function buildImportSpreadsheet(array $rows): UploadedFile
{
    $path = storage_path('app/test-import.xlsx');

    Excel::store(
        new class($rows) implements \Maatwebsite\Excel\Concerns\FromArray {
            public function __construct(private array $rows) {}
            public function array(): array
            {
                return array_merge([['nom', 'prenom', 'contact']], $this->rows);
            }
        },
        'test-import.xlsx',
        'local'
    );

    return new UploadedFile(storage_path('app/test-import.xlsx'), 'import.xlsx', null, null, true);
}

it('blocks a coach from importing', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Coach]))
        ->get(route('admin.filles.import'))
        ->assertForbidden();
});

it('previews rows and flags a duplicate by nom+prenom', function () {
    Fille::factory()->create(['nom' => 'Dossou', 'prenom' => 'Grâce']);

    $file = buildImportSpreadsheet([
        ['Hounkpatin', 'Sènami', '+229 01 00 00 00 00'],
        ['Dossou', 'Grâce', ''],
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('admin.filles.import.preview'), ['fichier' => $file]);

    $response->assertOk();
    $response->assertSee('Hounkpatin');
    $response->assertSee('doublon potentiel', false);

    expect(Fille::count())->toBe(1);
});

it('confirms the import and only creates the rows kept by the admin', function () {
    $file = buildImportSpreadsheet([
        ['Kpossou', 'Yasmine', ''],
        ['Ahouandjinou', 'Lucrèce', ''],
    ]);

    $this->actingAs($this->admin)->post(route('admin.filles.import.preview'), ['fichier' => $file]);

    $response = $this->actingAs($this->admin)->post(route('admin.filles.import.confirm'), [
        'lignes' => [0, 1],
    ]);

    $response->assertRedirect(route('admin.filles.index'));

    expect(Fille::count())->toBe(2);
    expect(Fille::where('nom', 'Kpossou')->exists())->toBeTrue();
    $created = Fille::where('nom', 'Kpossou')->first();
    expect($created->pin)->toMatch('/^\d{4}$/');
});
```

- [ ] **Step 3: Run the test to verify it fails**

```bash
vendor/bin/pest tests/Feature/Admin/FilleImportTest.php
```

Expected: FAIL — routes, controller, and import class do not exist yet.

- [ ] **Step 4: Create the import reader**

`app/Imports/FillesPreviewImport.php`:

```php
<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FillesPreviewImport implements ToCollection, WithHeadingRow
{
    public array $rows = [];

    public function collection($rows): void
    {
        $this->rows = $rows->map(fn ($row) => [
            'nom' => trim((string) ($row['nom'] ?? '')),
            'prenom' => trim((string) ($row['prenom'] ?? '')),
            'contact' => trim((string) ($row['contact'] ?? '')) ?: null,
        ])->values()->all();
    }
}
```

- [ ] **Step 5: Create the controller**

`app/Http/Controllers/Admin/FilleImportController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\FillesPreviewImport;
use App\Models\Fille;
use App\Services\PinGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class FilleImportController extends Controller
{
    public function form(): View
    {
        return view('admin.filles.import');
    }

    public function preview(Request $request): View
    {
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $import = new FillesPreviewImport();
        Excel::import($import, $request->file('fichier'));

        $rows = collect($import->rows)->map(function (array $row) {
            $row['doublon'] = Fille::where('nom', $row['nom'])
                ->where('prenom', $row['prenom'])
                ->exists();

            return $row;
        })->all();

        $request->session()->put('import_filles_rows', $rows);

        return view('admin.filles.import-preview', ['rows' => $rows]);
    }

    public function confirm(Request $request, PinGenerator $pinGenerator): RedirectResponse
    {
        $validated = $request->validate([
            'lignes' => ['required', 'array'],
            'lignes.*' => ['integer'],
        ]);

        $rows = $request->session()->get('import_filles_rows', []);

        foreach ($validated['lignes'] as $index) {
            if (! isset($rows[$index])) {
                continue;
            }

            $row = $rows[$index];

            Fille::create([
                'nom' => $row['nom'],
                'prenom' => $row['prenom'],
                'contact' => $row['contact'],
                'pin' => $pinGenerator->generate(),
                'date_entree' => now()->toDateString(),
            ]);
        }

        $request->session()->forget('import_filles_rows');

        return redirect()->route('admin.filles.index')->with('message', 'Import terminé.');
    }
}
```

- [ ] **Step 6: Register the routes**

In `routes/web.php`, inside the `admin.` group:

```php
use App\Http\Controllers\Admin\FilleImportController;

Route::get('filles/import', [FilleImportController::class, 'form'])->name('filles.import');
Route::post('filles/import/preview', [FilleImportController::class, 'preview'])->name('filles.import.preview');
Route::post('filles/import/confirm', [FilleImportController::class, 'confirm'])->name('filles.import.confirm');
```

- [ ] **Step 7: Create the views**

`resources/views/admin/filles/import.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Importer des filles depuis Excel</h1>
    </x-slot>

    <p>Colonnes attendues : nom, prenom, contact (optionnel).</p>

    <form action="{{ route('admin.filles.import.preview') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="file" name="fichier" accept=".xlsx,.xls" required>
        @error('fichier') <p>{{ $message }}</p> @enderror
        <button type="submit">Aperçu</button>
    </form>
</x-app-layout>
```

`resources/views/admin/filles/import-preview.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h1>Aperçu de l'import</h1>
    </x-slot>

    <form action="{{ route('admin.filles.import.confirm') }}" method="POST">
        @csrf

        <table>
            <thead>
                <tr>
                    <th></th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Contact</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $index => $row)
                    <tr>
                        <td>
                            <input type="checkbox" name="lignes[]" value="{{ $index }}"
                                   {{ $row['doublon'] ? '' : 'checked' }}>
                        </td>
                        <td>{{ $row['nom'] }}</td>
                        <td>{{ $row['prenom'] }}</td>
                        <td>{{ $row['contact'] ?? '—' }}</td>
                        <td>{{ $row['doublon'] ? 'doublon potentiel' : 'nouvelle fiche' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <button type="submit">Confirmer l'import des lignes cochées</button>
    </form>
</x-app-layout>
```

- [ ] **Step 8: Run the test to verify it passes**

```bash
vendor/bin/pest tests/Feature/Admin/FilleImportTest.php
```

Expected: PASS.

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint
git add -A
git commit -m "feat: add fille Excel import with duplicate preview"
```

---

## Task 8: Security headers

**Files:**
- Create: `app/Http/Middleware/SecurityHeaders.php`
- Modify: `bootstrap/app.php` (append the middleware globally)
- Test: `tests/Feature/SecurityHeadersTest.php`

**Interfaces:**
- Produces: every response carries `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and a strict `Content-Security-Policy` (`default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'`) — the same policy Caisse CAFAB enforces, so no inline `style=` or third-party CDN asset can silently work in either app.

- [ ] **Step 1: Write the failing test**

`tests/Feature/SecurityHeadersTest.php`:

```php
<?php

it('adds strict security headers to every response', function () {
    $response = $this->get('/login');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader(
        'Content-Security-Policy',
        "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'"
    );
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/pest tests/Feature/SecurityHeadersTest.php
```

Expected: FAIL — headers are not set yet.

- [ ] **Step 3: Implement the middleware**

`app/Http/Middleware/SecurityHeaders.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'"
        );

        return $response;
    }
}
```

- [ ] **Step 4: Register it globally**

In `bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware) { ... })`, add:

```php
$middleware->append(\App\Http\Middleware\SecurityHeaders::class);
```

- [ ] **Step 5: Run the test to verify it passes**

```bash
vendor/bin/pest tests/Feature/SecurityHeadersTest.php
```

Expected: PASS.

- [ ] **Step 6: Run the full suite**

```bash
vendor/bin/pest
vendor/bin/pint --test
```

Expected: full suite green, no style issues.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add strict security headers"
```

---

## End of plan checklist

- [ ] `php artisan test` (or `vendor/bin/pest`) passes in full.
- [ ] `vendor/bin/pint --test` reports no issues.
- [ ] An admin can log in (seed one manually via `php artisan users:create "Nom" email@cafab.bj --role=admin`), reach `/admin/coaches` and `/admin/filles`, create/edit/deactivate/regenerate-pin on both, and import filles from an `.xlsx` file with duplicate detection.
- [ ] A coach account exists and is blocked (403) from every `admin.*` route.
- [ ] No inline `style="..."` was introduced anywhere in the Blade views written in this plan.
- [ ] Next plan: **Planning & pointage** (créneaux récurrents/extraordinaires, mode kiosque, pointage coach, calcul de ponctualité, calendrier) — do not start it until this plan's checklist above is fully green.
