# Mise en production Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Présence & Paiements CAFAB ready to go live on the cPanel shared hosting it shares with Caisse CAFAB: slow down PIN guessing on the public kiosk, replace Laravel's English error pages, and ship one-command deploy and rollback scripts with the production configuration and go-live procedure.

**Architecture:** A small `VerrouKiosque` service counts wrong PINs per IP in the application cache and blocks identification for 15 minutes at the 10th; `Kiosque\IdentificationController::identifier` consults it before and after each attempt. One exception render callback in `bootstrap/app.php` turns 419 and 429 on `kiosque/*` into a redirect to the PIN screen; everything else falls through to new French `resources/views/errors/*.blade.php` pages that never touch the database. Deployment reuses the proven releases/current symlink scripts of `caisse-depenses`, adapted (PHP 8.3 guard, rollback to the release that was in service, `public/` re-synced on every switch, `.well-known` preserved, cron reminder).

**Tech Stack:** Laravel 12, PHP 8.3+, Blade, Pest, Laravel Pint, SQLite in tests, Bash (GNU coreutils on the server, Git Bash locally).

**Spec:** `docs/superpowers/specs/2026-09-26-deploiement-design.md` (approved by Elisée on 2026-09-26). Reference scripts: `C:\wamp64\www\caisse_cafab\caisse-depenses\deploy\deploy-shared-hosting.sh`, `rollback-shared-hosting.sh`, `bin\deploy`.

## Global Constraints

- PHP 8.3+, Laravel 12, Pest. Verify with `php artisan test` and `vendor/bin/pint --dirty` before every commit.
- No new Composer or npm dependency.
- Content-Security-Policy (`app/Http/Middleware/SecurityHeaders.php`: `style-src 'self'`, `script-src 'self' 'unsafe-eval'`): never write a `style="..."` attribute or an inline `<script>` block.
- UI copy is French. Kiosk screens (used by the filles) say "tu"; every other screen says "vous". Use the exact strings given in the tasks, they come from the spec.
- Never log a PIN. The kiosk lock logs the IP address only.
- Never run `migrate`, `migrate:fresh` or `db:seed` from the worktree against a real database. Tests run on in-memory SQLite (`phpunit.xml`). Nothing in this plan touches the production server or Caisse CAFAB.
- `npm install`/`npm ci` in the worktree can rewrite `package-lock.json`. Never commit that change: `git checkout -- package-lock.json` before committing.
- Shell scripts: `#!/usr/bin/env bash`, `set -euo pipefail`, comments in French without accents (same convention as the Caisse CAFAB scripts). LF line endings are already enforced for the whole repo by `.gitattributes` (`* text=auto eol=lf`).
- Commit messages end with a blank line then `Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>`.

## Review Focus

- A `pin` sent as an array (`pin[]=1`) or missing altogether: must count as a wrong code and redirect, never a 500 (a guest-reachable 500 already bit this app once). Pinned in Task 1.
- The database is down in production: every error page must still render, so none may run a query. Pinned in Task 2.
- An error on a kiosk address other than 419/429 (for example `/kiosque/adresse-inconnue`): must show the French 404 page, not bounce to the PIN screen. Pinned in Task 2.
- `bin/deploy` given a branch or PHP path containing shell characters (`main;id`): must refuse before any SSH connection, since the value is pasted into a remote command. Pinned in Task 3.
- `deploy.sh` started with no `shared/.env`, a missing PHP or a PHP older than 8.3: must stop before cloning anything, with a message that says what to fix. Pinned in Task 3.

---

### Task 1: Block the kiosk after repeated wrong PINs

**Files:**
- Create: `app/Services/VerrouKiosque.php`
- Modify: `app/Http/Controllers/Kiosque/IdentificationController.php` (method `identifier` and imports)
- Test: `tests/Feature/Kiosque/VerrouKiosqueTest.php`

**Interfaces:**
- Consumes: `App\Services\KioskIdentifier::identifier(string $pin): ?Model` (existing), route `kiosque.identifier` with `throttle:20,1` (existing, unchanged).
- Produces: `App\Services\VerrouKiosque` with `estBloque(string $ip): bool`, `enregistrerEchec(string $ip): void`, `messageBlocage(string $ip): string`, constants `SEUIL_CODES_FAUX = 10`, `DUREE_SECONDES = 900`. Not used by other tasks.

Behaviour (spec § 5.1): every wrong code (unknown, wrong length, missing, not a string) counts for `$request->ip()`. At the 10th wrong code inside the counting window, identification is blocked for that IP for 15 minutes from that 10th code; during the block every code is refused, a good one included, and nothing is stored in the session. Good codes neither count nor reset the counter. Each block start writes one `warning` log line with the IP. The counting window is Laravel's fixed rate-limiter window: it opens at the first wrong code and lasts 15 minutes.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Kiosque/VerrouKiosqueTest.php`:

```php
<?php

use App\Models\Fille;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

const MESSAGE_BLOCAGE_15_MIN = 'Trop de codes erronés. Réessaie dans 15 minutes, ou demande à ton coach de te pointer.';

function envoyerCodesFauxAuKiosque(TestCase $test, int $nombre, string $ip = '127.0.0.1'): void
{
    foreach (range(1, $nombre) as $essai) {
        $test->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('kiosque.identifier'), ['pin' => '0000']);
    }
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-27 17:00:00');
    Fille::factory()->create(['pin' => '1234']);
});

afterEach(fn () => Carbon::setTestNow());

it('still accepts a good code after nine wrong ones', function () {
    envoyerCodesFauxAuKiosque($this, 9);

    $this->post(route('kiosque.identifier'), ['pin' => '1234'])
        ->assertRedirect(route('kiosque.menu'));
});

it('announces the block on the tenth wrong code', function () {
    envoyerCodesFauxAuKiosque($this, 9);

    $this->post(route('kiosque.identifier'), ['pin' => '0000'])
        ->assertRedirect(route('kiosque.home'))
        ->assertSessionHasErrors(['pin' => MESSAGE_BLOCAGE_15_MIN]);
});

it('refuses even a good code while the address is blocked', function () {
    envoyerCodesFauxAuKiosque($this, 10);

    $this->post(route('kiosque.identifier'), ['pin' => '1234'])
        ->assertRedirect(route('kiosque.home'))
        ->assertSessionHasErrors(['pin' => MESSAGE_BLOCAGE_15_MIN]);

    expect(session('kiosque.identifie'))->toBeNull();
});

it('rounds the remaining time up to the minute, in the singular for one minute', function () {
    envoyerCodesFauxAuKiosque($this, 10);
    Carbon::setTestNow(now()->addMinutes(14)->addSeconds(30));

    $this->post(route('kiosque.identifier'), ['pin' => '1234'])
        ->assertSessionHasErrors(['pin' => 'Trop de codes erronés. Réessaie dans 1 minute, ou demande à ton coach de te pointer.']);
});

it('lets a good code through again 15 minutes after the block started', function () {
    envoyerCodesFauxAuKiosque($this, 10);
    Carbon::setTestNow(now()->addMinutes(15)->addSecond());

    $this->post(route('kiosque.identifier'), ['pin' => '1234'])
        ->assertRedirect(route('kiosque.menu'));
});

it('keeps counting wrong codes across good ones', function () {
    envoyerCodesFauxAuKiosque($this, 5);
    $this->post(route('kiosque.identifier'), ['pin' => '1234'])->assertRedirect(route('kiosque.menu'));
    envoyerCodesFauxAuKiosque($this, 5);

    $this->post(route('kiosque.identifier'), ['pin' => '1234'])
        ->assertSessionHasErrors(['pin' => MESSAGE_BLOCAGE_15_MIN]);
});

it('does not block another address', function () {
    envoyerCodesFauxAuKiosque($this, 10, '10.0.0.1');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->post(route('kiosque.identifier'), ['pin' => '1234'])
        ->assertRedirect(route('kiosque.menu'));
});

it('counts malformed or missing codes as wrong codes, without ever failing', function () {
    $envois = [['pin' => '12'], ['pin' => '12345'], [], ['pin' => ['1', '2', '3', '4']], ['pin' => '']];

    foreach (range(1, 2) as $tour) {
        foreach ($envois as $donnees) {
            $this->post(route('kiosque.identifier'), $donnees)->assertRedirect(route('kiosque.home'));
        }
    }

    $this->post(route('kiosque.identifier'), ['pin' => '1234'])
        ->assertSessionHasErrors(['pin' => MESSAGE_BLOCAGE_15_MIN]);
});

it('logs the address once when the block starts, never the code', function () {
    Log::spy();

    envoyerCodesFauxAuKiosque($this, 12);

    Log::shouldHaveReceived('warning')
        ->with('Kiosque : identification bloquée après trop de codes erronés.', ['ip' => '127.0.0.1'])
        ->once();
});
```

Why these numbers stay under `throttle:20,1`: the largest test sends 12 requests at the same frozen minute.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=VerrouKiosqueTest`
Expected: FAIL. "still accepts a good code after nine wrong ones", "does not block another address" and "lets a good code through again…" may already pass; every test that expects the block message fails (the controller says `Code inconnu.` or redirects to the menu), and the log test fails with "should be called exactly 1 times".

- [ ] **Step 3: Create the service**

Create `app/Services/VerrouKiosque.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Freine les essais de codes PIN au kiosque, ouvert sur internet : au 10e code
 * faux d'une même adresse IP, l'identification est bloquée 15 minutes pour
 * cette adresse, bons codes compris. Les bons codes ne comptent pas.
 */
class VerrouKiosque
{
    public const SEUIL_CODES_FAUX = 10;

    public const DUREE_SECONDES = 900;

    public function estBloque(string $ip): bool
    {
        return RateLimiter::tooManyAttempts($this->cleBlocage($ip), 1);
    }

    public function enregistrerEchec(string $ip): void
    {
        $codesFaux = RateLimiter::hit($this->cleCodesFaux($ip), self::DUREE_SECONDES);

        if ($codesFaux < self::SEUIL_CODES_FAUX) {
            return;
        }

        // Le compteur repart de zéro : à la fin du blocage, l'adresse a de
        // nouveau droit à 10 essais.
        RateLimiter::clear($this->cleCodesFaux($ip));
        RateLimiter::hit($this->cleBlocage($ip), self::DUREE_SECONDES);

        Log::warning('Kiosque : identification bloquée après trop de codes erronés.', ['ip' => $ip]);
    }

    public function messageBlocage(string $ip): string
    {
        $minutes = max(1, (int) ceil(RateLimiter::availableIn($this->cleBlocage($ip)) / 60));
        $unite = $minutes > 1 ? 'minutes' : 'minute';

        return "Trop de codes erronés. Réessaie dans {$minutes} {$unite}, ou demande à ton coach de te pointer.";
    }

    private function cleCodesFaux(string $ip): string
    {
        return 'kiosque:codes-faux:'.$ip;
    }

    private function cleBlocage(string $ip): string
    {
        return 'kiosque:blocage:'.$ip;
    }
}
```

- [ ] **Step 4: Use it in the controller**

In `app/Http/Controllers/Kiosque/IdentificationController.php`, add the imports (keep them sorted with the existing ones):

```php
use App\Services\VerrouKiosque;
use Illuminate\Support\Facades\Validator;
```

Replace the beginning of `identifier()`, from its signature down to and including the `if (! $personne) { … }` block, with:

```php
    public function identifier(Request $request, KioskIdentifier $identifier, VerrouKiosque $verrou): RedirectResponse
    {
        $ip = (string) $request->ip();

        if ($verrou->estBloque($ip)) {
            return redirect()->route('kiosque.home')->withErrors(['pin' => $verrou->messageBlocage($ip)]);
        }

        // Pas de $request->validate() : il renverrait avant le compteur, alors
        // qu'un code au mauvais format compte aussi comme un code faux.
        $validation = Validator::make($request->only('pin'), ['pin' => ['required', 'string', 'size:4']]);
        $personne = $validation->fails() ? null : $identifier->identifier($request->string('pin')->value());

        if (! $personne) {
            $verrou->enregistrerEchec($ip);

            $message = match (true) {
                $verrou->estBloque($ip) => $verrou->messageBlocage($ip),
                $validation->fails() => $validation->errors()->first('pin'),
                default => 'Code inconnu.',
            };

            return redirect()->route('kiosque.home')->withErrors(['pin' => $message]);
        }
```

Leave the rest of the method (session writes and redirect to `kiosque.menu`) unchanged.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter="VerrouKiosqueTest|IdentificationTest|PointageTest|CachetDeclarationTest"`
Expected: PASS. The existing "rate-limits repeated pin attempts" test still passes: its 21st request is refused by `throttle:20,1` before the controller runs.

- [ ] **Step 6: Full suite, format, commit**

Run: `php artisan test` (expected: all green) and `vendor/bin/pint --dirty`.

```bash
git checkout -- package-lock.json 2>/dev/null
git add app/Services/VerrouKiosque.php app/Http/Controllers/Kiosque/IdentificationController.php tests/Feature/Kiosque/VerrouKiosqueTest.php
git commit -m "feat(kiosque): bloquer une adresse après 10 codes PIN erronés

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: French error pages, and the kiosk never stuck on an error

**Files:**
- Modify: `bootstrap/app.php` (the `withExceptions` closure and imports)
- Create: `resources/views/errors/layout.blade.php`, `resources/views/errors/403.blade.php`, `404.blade.php`, `419.blade.php`, `429.blade.php`, `500.blade.php`, `503.blade.php`
- Modify: `tests/Feature/Kiosque/IdentificationTest.php` (test "rate-limits repeated pin attempts")
- Test: `tests/Feature/PagesErreurTest.php`

**Interfaces:**
- Consumes: route `kiosque.home` (existing); the kiosk home shows `@error('pin')` (existing, `resources/views/kiosque/accueil.blade.php`).
- Produces: nothing used by later tasks.

Laravel 12 fact this task relies on (`vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php`, `render()`): `prepareException()` turns a `TokenMismatchException` into `HttpException(419)` **before** the render callbacks run, and `ThrottleRequestsException` is already an `HttpException(429)`. So one callback on `HttpExceptionInterface` sees both.

Blade fact this task relies on: `@section('titre', '…')` with an inline string escapes it with `e()`, so an apostrophe renders as `&#039;`. The layout prints its button label with `{{ }}` for the same result. Default `assertSee()` escapes its needle the same way, so tests use plain strings.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/PagesErreurTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('shows a French 404 page with the CAFAB logo and a way back', function () {
    $this->get('/cette-page-n-existe-pas')
        ->assertNotFound()
        ->assertSee('Page introuvable')
        ->assertSee('Cette adresse ne correspond à aucune page.')
        ->assertSee('images/logo-cafab.png', false)
        ->assertSee("Retour à l'accueil");
});

it('shows a French 403 page', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get('/admin/ping')
        ->assertForbidden()
        ->assertSee('Accès refusé')
        ->assertSee("Vous n'avez pas les droits pour ouvrir cette page.");
});

it('shows a French 419 page outside the kiosk', function () {
    Route::middleware('web')->post('/essai-page-expiree', fn () => throw new TokenMismatchException);

    $this->post('/essai-page-expiree')
        ->assertStatus(419)
        ->assertSee('Page expirée')
        ->assertSee('La page est restée ouverte trop longtemps. Rechargez-la puis recommencez.');
});

it('shows a French 429 page outside the kiosk', function () {
    Route::middleware('web')->get('/essai-trop-de-tentatives', fn () => throw new ThrottleRequestsException);

    $this->get('/essai-trop-de-tentatives')
        ->assertStatus(429)
        ->assertSee('Trop de tentatives')
        ->assertSee('Patientez un peu avant de réessayer.');
});

it('shows a French 500 page without any technical detail', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/essai-erreur', fn () => throw new RuntimeException('Détail interne à ne pas montrer'));

    $this->get('/essai-erreur')
        ->assertStatus(500)
        ->assertSee('Erreur inattendue')
        ->assertSee('Un problème est survenu de notre côté. Réessayez dans un instant.')
        ->assertDontSee('Détail interne à ne pas montrer')
        ->assertDontSee('RuntimeException');
});

it('shows a French 503 page without the way back', function () {
    Route::get('/essai-maintenance', fn () => abort(503));

    $this->get('/essai-maintenance')
        ->assertStatus(503)
        ->assertSee('Maintenance en cours')
        ->assertSee("L'application revient dans quelques minutes.")
        ->assertDontSee("Retour à l'accueil");
});

it('renders every error page without querying the database', function (int $code) {
    DB::enableQueryLog();

    view("errors.{$code}")->render();

    expect(DB::getQueryLog())->toBeEmpty();
})->with([403, 404, 419, 429, 500, 503]);

it('sends the kiosk back to the code screen when its page has expired', function () {
    Route::middleware('web')->post('/kiosque/essai-page-expiree', fn () => throw new TokenMismatchException);

    $this->post('/kiosque/essai-page-expiree')
        ->assertRedirect(route('kiosque.home'))
        ->assertSessionHasErrors(['pin' => 'La page avait expiré. Tape à nouveau ton code.']);
});

it('keeps the French 404 page for an unknown kiosk address', function () {
    $this->get('/kiosque/adresse-inconnue')
        ->assertNotFound()
        ->assertSee('Page introuvable');
});
```

In `tests/Feature/Kiosque/IdentificationTest.php`, replace the test "rate-limits repeated pin attempts" with:

```php
it('rate-limits repeated pin attempts and says so on the kiosk', function () {
    for ($i = 0; $i < 21; $i++) {
        $response = $this->post(route('kiosque.identifier'), ['pin' => '0000']);
    }

    $response->assertRedirect(route('kiosque.home'))
        ->assertSessionHasErrors(['pin' => "Trop d'essais en peu de temps. Attends une minute puis réessaie."]);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter="PagesErreurTest|IdentificationTest"`
Expected: FAIL. The French texts are missing (Laravel's English pages render instead), `view("errors.403")` throws "View [errors.403] not found", the kiosk 419 test gets status 419 instead of a redirect, and the new throttle test gets 429 instead of a redirect. "keeps the French 404 page…" fails on the missing text.

- [ ] **Step 3: Create the error layout and pages**

Create `resources/views/errors/layout.blade.php`:

```blade
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-cafab.png') }}">
    @vite(['resources/css/app.scss'])
</head>
<body>
    {{-- Aucune requête en base ici (ni horloge, ni session) : la page doit
         s'afficher même quand la base de données ne répond pas. --}}
    <div class="auth-shell">
        <div class="auth-card">
            <div class="logo-box">
                <img src="{{ asset('images/logo-cafab.png') }}" alt="CAFAB">
            </div>
            <p class="overline mb-1">Présence &amp; paiements</p>
            <h1 class="page-title">@yield('titre')</h1>
            <p class="field-hint mb-4">@yield('message')</p>

            @unless ($sansRetour ?? false)
                <a href="{{ url('/') }}" class="btn-ink w-100 justify-content-center">{{ "Retour à l'accueil" }}</a>
            @endunless
        </div>
    </div>
</body>
</html>
```

Create the six pages, each exactly as below.

`resources/views/errors/403.blade.php`:

```blade
@extends('errors.layout')

@section('titre', 'Accès refusé')
@section('message', "Vous n'avez pas les droits pour ouvrir cette page.")
```

`resources/views/errors/404.blade.php`:

```blade
@extends('errors.layout')

@section('titre', 'Page introuvable')
@section('message', 'Cette adresse ne correspond à aucune page.')
```

`resources/views/errors/419.blade.php`:

```blade
@extends('errors.layout')

@section('titre', 'Page expirée')
@section('message', 'La page est restée ouverte trop longtemps. Rechargez-la puis recommencez.')
```

`resources/views/errors/429.blade.php`:

```blade
@extends('errors.layout')

@section('titre', 'Trop de tentatives')
@section('message', 'Patientez un peu avant de réessayer.')
```

`resources/views/errors/500.blade.php`:

```blade
@extends('errors.layout')

@section('titre', 'Erreur inattendue')
@section('message', 'Un problème est survenu de notre côté. Réessayez dans un instant.')
```

`resources/views/errors/503.blade.php`:

```blade
@extends('errors.layout', ['sansRetour' => true])

@section('titre', 'Maintenance en cours')
@section('message', "L'application revient dans quelques minutes.")
```

- [ ] **Step 4: Send kiosk 419 and 429 back to the PIN screen**

In `bootstrap/app.php`, add these imports after the existing `use` lines:

```php
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
```

Replace

```php
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

with

```php
    ->withExceptions(function (Exceptions $exceptions): void {
        // Le kiosque ne reste jamais sur une page d'erreur : une page expirée
        // (tablette restée en veille) ou trop d'essais ramènent à l'écran du
        // code. Les autres erreurs, et tout le reste de l'application, gardent
        // les pages de resources/views/errors.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('kiosque', 'kiosque/*') || $request->expectsJson()) {
                return null;
            }

            $message = match ($e->getStatusCode()) {
                419 => 'La page avait expiré. Tape à nouveau ton code.',
                429 => "Trop d'essais en peu de temps. Attends une minute puis réessaie.",
                default => null,
            };

            return $message === null
                ? null
                : redirect()->route('kiosque.home')->withErrors(['pin' => $message]);
        });
    })->create();
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter="PagesErreurTest|IdentificationTest|VerrouKiosqueTest"`
Expected: PASS.

- [ ] **Step 6: Build, full suite, format, commit**

Run: `npm run build` (the error layout uses the existing `resources/css/app.scss` entry, nothing new to build, but the tests read the manifest), then `php artisan test` (all green) and `vendor/bin/pint --dirty`.

```bash
git checkout -- package-lock.json 2>/dev/null
git add bootstrap/app.php resources/views/errors tests/Feature/PagesErreurTest.php tests/Feature/Kiosque/IdentificationTest.php
git commit -m "feat: pages d'erreur en français, retour du kiosque sur 419 et 429

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Deploy and rollback scripts

**Files:**
- Create: `deploy/deploy-shared-hosting.sh`, `deploy/rollback-shared-hosting.sh`, `bin/deploy`

**Interfaces:**
- Consumes: the Caisse CAFAB scripts listed in the header (read them first; the new scripts must differ from them only where this task says so).
- Produces (used by Task 4's README): server layout under `~/presence.fillesdartsbenin.com` (`releases/`, `shared/.env`, `shared/storage/`, `shared/deploy.log`, `current`, root copies `deploy.sh` and `rollback.sh`); script variables `APP_DIR`, `REPO_URL`, `BRANCH`, `HEALTHCHECK_URL`, `PHP_BIN`, `COMPOSER_BIN`; local variables `PRESENCE_DEPLOY_HOST`, `PRESENCE_DEPLOY_PORT`, `PRESENCE_DEPLOY_KEY`, `PRESENCE_DEPLOY_APP_DIR`, `PRESENCE_DEPLOY_PHP_BIN`; commands `bin/deploy`, `bin/deploy <branche>`, `bin/deploy rollback`.

Differences from the Caisse CAFAB scripts, and why (spec § 3):
1. PHP 8.3 guard and `PHP_BIN`: this app needs PHP 8.3, Caisse only 8.2, and cPanel's default CLI may be older. Composer and artisan run through `$PHP_BIN`.
2. Automatic rollback returns to the release that was in service before the switch (recorded before switching), not "the second most recent".
3. Manual rollback returns to the release preceding the one `current` points to, in timestamp-name order, so two rollbacks in a row go back two releases.
4. `public/` is re-synced to the document root on every switch, rollbacks included. Otherwise a rollback keeps the abandoned release's `build/` assets at the root, and the restored pages ask for CSS/JS files that are no longer there. The copy lives in a function `synchroniser_public`, duplicated identically in the two server scripts because each must run standalone on the server.
5. The root clean-up spares `.well-known` (cPanel certificate validation) and `cgi-bin`.
6. Old-release clean-up sorts by name and never deletes the release in service.
7. A cron reminder when no crontab line runs `schedule:run` for this app.
8. `bin/deploy` refuses branch names, directory names and PHP paths outside `[A-Za-z0-9._/-]`, because they are pasted into the remote command line.
9. The stale comment of the Caisse script about `optimize:clear` "ci-dessus" (never called there) is not copied.

- [ ] **Step 1: Write `deploy/deploy-shared-hosting.sh`**

```bash
#!/usr/bin/env bash
# Deploiement de presence-paiement-cafab (CAFAB) sur l'hebergement mutualise
# cPanel partage avec caisse-depenses (pas de sudo/systemctl/apt : PHP, MySQL,
# Composer, Node et Git sont fournis par l'hebergeur).
#
# Repris de caisse-depenses/deploy/deploy-shared-hosting.sh. Differences :
# controle de PHP 8.3 (PHP_BIN), retour arriere vers la version qui etait en
# service, public/ resynchronise a chaque bascule, .well-known et cgi-bin
# preserves a la racine, rappel du cron du planificateur.
#
# Pattern releases/current : bascule atomique par symlink, retour arriere
# automatique si le healthcheck echoue apres la bascule.
set -euo pipefail
trap 'echo "[ERREUR] deploy-shared-hosting.sh a echoue a la ligne $LINENO - annulation." >&2' ERR

APP_DIR="${APP_DIR:-$HOME/presence.fillesdartsbenin.com}"
REPO_URL="${REPO_URL:-git@github.com:Magloire04/cafab_bord.git}"
BRANCH="${BRANCH:-main}"
KEEP_RELEASES=5
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://presence.fillesdartsbenin.com/up}"
PHP_BIN="${PHP_BIN:-php}"
PHP_83_EXEMPLE="/opt/cpanel/ea-php83/root/usr/bin/php"

RELEASE="$(date +%Y%m%d%H%M%S)"
RELEASE_DIR="${APP_DIR}/releases/${RELEASE}"

# Recopie public/ d'une version a la racine du sous-domaine. La racine de
# document (Document Root) cPanel EST ${APP_DIR} (pas de "current/public"
# configurable sans l'interface cPanel) : index.php y est adapte pour pointer
# vers le symlink stable "current/" plutot que "../".
# A garder identique dans rollback-shared-hosting.sh.
synchroniser_public() {
  local version="$1"

  find "${APP_DIR}" -maxdepth 1 -mindepth 1 \
    ! -name releases ! -name shared ! -name current ! -name current.new \
    ! -name deploy.sh ! -name rollback.sh ! -name .well-known ! -name cgi-bin \
    -exec rm -rf {} +
  cp -a "${version}/public/." "${APP_DIR}/"
  sed -i \
    -e "s#__DIR__\.'/\.\./storage/framework/maintenance\.php'#__DIR__.'/current/storage/framework/maintenance.php'#" \
    -e "s#__DIR__\.'/\.\./vendor/autoload\.php'#__DIR__.'/current/vendor/autoload.php'#" \
    -e "s#__DIR__\.'/\.\./bootstrap/app\.php'#__DIR__.'/current/bootstrap/app.php'#" \
    "${APP_DIR}/index.php"

  # Les 3 remplacements doivent avoir eu lieu : mieux vaut echouer bruyamment
  # que laisser un index.php casse en production.
  if grep -q "__DIR__\.'/\.\./" "${APP_DIR}/index.php"; then
    echo "ERREUR: index.php contient encore une reference '../' non adaptee - verifier public/index.php." >&2
    return 1
  fi

  # Le lien public/storage cree par `artisan storage:link` est relatif a
  # public/ ; recopie a la racine, sa cible ne serait plus la bonne.
  rm -f "${APP_DIR}/storage"
  ln -s shared/storage/app/public "${APP_DIR}/storage"
}

echo "==> Deploiement release ${RELEASE} (branche ${BRANCH})"

# --- 0. Garde-fous, avant toute action ---------------------------------------
if [[ ! -f "${APP_DIR}/shared/.env" ]]; then
  echo "ERREUR: ${APP_DIR}/shared/.env introuvable. Creez-le avant de deployer (voir README, section Deploiement)." >&2
  exit 1
fi

if ! command -v "${PHP_BIN}" > /dev/null 2>&1; then
  echo "ERREUR: PHP introuvable (${PHP_BIN}). Reglez PHP_BIN sur le PHP 8.3 de cPanel, par exemple ${PHP_83_EXEMPLE}." >&2
  exit 1
fi

if ! "${PHP_BIN}" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then
  echo "ERREUR: ${PHP_BIN} est en PHP $("${PHP_BIN}" -r 'echo PHP_VERSION;'), il faut PHP 8.3 ou plus. Reglez PHP_BIN sur le PHP 8.3 de cPanel, par exemple ${PHP_83_EXEMPLE}." >&2
  exit 1
fi

COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer || true)}"
if [[ -z "${COMPOSER_BIN}" ]]; then
  echo "ERREUR: composer introuvable. Reglez COMPOSER_BIN sur le chemin de composer." >&2
  exit 1
fi

# --- 1. Recuperation du code --------------------------------------------------
git clone --depth 1 --branch "${BRANCH}" "${REPO_URL}" "${RELEASE_DIR}"
COMMIT_SHA="$(git -C "${RELEASE_DIR}" rev-parse --short HEAD)"

# --- 2. Liens vers les ressources partagees (jamais recreees) ----------------
ln -s "${APP_DIR}/shared/.env" "${RELEASE_DIR}/.env"
rm -rf "${RELEASE_DIR}/storage"
ln -s "${APP_DIR}/shared/storage" "${RELEASE_DIR}/storage"

cd "${RELEASE_DIR}"

# --- 3. Dependances, par le PHP 8.3 retenu et non celui du shell -------------
"${PHP_BIN}" "${COMPOSER_BIN}" install --no-dev --optimize-autoloader --no-interaction

# --- 4. Cle d'application (uniquement si absente du .env partage) ------------
if ! grep -q '^APP_KEY=base64:' "${APP_DIR}/shared/.env"; then
  "${PHP_BIN}" artisan key:generate --force
fi

# --- 5. Migrations (avant bascule : l'ancienne release sert encore le trafic)
# Une migration qui echoue (par exemple celle des emails sur un doublon) arrete
# le script ici : le site reste sur l'ancienne version.
"${PHP_BIN}" artisan migrate --force

# --- 6. Assets front (Vite) ---------------------------------------------------
npm ci
npm run build

# --- 7. Caches Laravel ---------------------------------------------------------
"${PHP_BIN}" artisan config:cache
"${PHP_BIN}" artisan route:cache
"${PHP_BIN}" artisan view:cache
"${PHP_BIN}" artisan storage:link

# --- 8. Bascule atomique du symlink -------------------------------------------
# On note la version en service pour y revenir si le healthcheck echoue.
ANCIENNE_VERSION=""
if [[ -L "${APP_DIR}/current" ]]; then
  ANCIENNE_VERSION="$(readlink -f "${APP_DIR}/current")"
fi
ln -s "${RELEASE_DIR}" "${APP_DIR}/current.new"
mv -Tf "${APP_DIR}/current.new" "${APP_DIR}/current"

# Pas de sudo/systemctl ici : LSAPI/CloudLinux relit le code a chaque requete,
# la bascule du symlink suffit.

# --- 9. public/ a la racine du sous-domaine -----------------------------------
synchroniser_public "${RELEASE_DIR}"

# --- 10. Healthcheck - echec = retour immediat a la version precedente -------
sleep 2
if ! curl -fsS --max-time 10 "${HEALTHCHECK_URL}" > /dev/null; then
  echo "ERREUR: healthcheck KO sur ${HEALTHCHECK_URL}." >&2
  if [[ -n "${ANCIENNE_VERSION}" ]]; then
    echo "==> Retour a la version precedente $(basename "${ANCIENNE_VERSION}")." >&2
    ln -s "${ANCIENNE_VERSION}" "${APP_DIR}/current.new"
    mv -Tf "${APP_DIR}/current.new" "${APP_DIR}/current"
    synchroniser_public "${ANCIENNE_VERSION}"
  else
    echo "Premier deploiement : aucune version precedente, le site reste sur ${RELEASE}." >&2
  fi
  echo "$(date -Iseconds) release=${RELEASE} commit=${COMMIT_SHA} branch=${BRANCH} statut=ECHEC_HEALTHCHECK" \
    >> "${APP_DIR}/shared/deploy.log"
  exit 1
fi

# --- 11. Nettoyage des anciennes releases (jamais celle en service) ----------
EN_SERVICE="$(readlink -f "${APP_DIR}/current")"
ls -1d "${APP_DIR}"/releases/*/ | sort -r | tail -n +$((KEEP_RELEASES + 1)) | while read -r ancienne; do
  if [[ "$(readlink -f "${ancienne}")" != "${EN_SERVICE}" ]]; then
    rm -rf "${ancienne}"
  fi
done

# --- 12. Auto-mise a jour des scripts pour le prochain deploiement ----------
# cp en place ecraserait le fichier que bash est en train de lire (ce script
# lui-meme) : on passe par un fichier temporaire puis un mv atomique.
cp "${RELEASE_DIR}/deploy/deploy-shared-hosting.sh" "${APP_DIR}/deploy.sh.new"
cp "${RELEASE_DIR}/deploy/rollback-shared-hosting.sh" "${APP_DIR}/rollback.sh.new"
chmod +x "${APP_DIR}/deploy.sh.new" "${APP_DIR}/rollback.sh.new"
mv -f "${APP_DIR}/deploy.sh.new" "${APP_DIR}/deploy.sh"
mv -f "${APP_DIR}/rollback.sh.new" "${APP_DIR}/rollback.sh"

# --- 13. Rappel du cron du planificateur (n'echoue pas) ----------------------
CRONTAB_ACTUELLE="$(crontab -l 2>/dev/null || true)"
if ! grep -Eq "$(basename "${APP_DIR}")/current.*schedule:run" <<< "${CRONTAB_ACTUELLE}"; then
  echo ""
  echo "ATTENTION: aucune tache cron ne lance le planificateur de cette application."
  echo "Ajoutez cette ligne dans cPanel, Taches Cron :"
  echo "  * * * * * cd ${APP_DIR}/current && ${PHP_BIN} artisan schedule:run >> /dev/null 2>&1"
  echo ""
fi

# --- 14. Journal d'audit (aucune donnee personnelle) --------------------------
echo "$(date -Iseconds) release=${RELEASE} commit=${COMMIT_SHA} branch=${BRANCH} statut=OK" \
  >> "${APP_DIR}/shared/deploy.log"

echo "==> Deploiement ${RELEASE} (commit ${COMMIT_SHA}) reussi."
```

- [ ] **Step 2: Write `deploy/rollback-shared-hosting.sh`**

```bash
#!/usr/bin/env bash
# Retour manuel de presence-paiement-cafab (CAFAB) a la version precedente, sur
# l'hebergement mutualise cPanel. Repris de caisse-depenses. Differences : la
# cible est la version qui precede celle en service (deux retours de suite
# reculent de deux versions), et public/ est resynchronise a la racine.
set -euo pipefail
trap 'echo "[ERREUR] rollback-shared-hosting.sh a echoue a la ligne $LINENO" >&2' ERR

APP_DIR="${APP_DIR:-$HOME/presence.fillesdartsbenin.com}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://presence.fillesdartsbenin.com/up}"

# Recopie public/ d'une version a la racine du sous-domaine.
# A garder identique dans deploy-shared-hosting.sh.
synchroniser_public() {
  local version="$1"

  find "${APP_DIR}" -maxdepth 1 -mindepth 1 \
    ! -name releases ! -name shared ! -name current ! -name current.new \
    ! -name deploy.sh ! -name rollback.sh ! -name .well-known ! -name cgi-bin \
    -exec rm -rf {} +
  cp -a "${version}/public/." "${APP_DIR}/"
  sed -i \
    -e "s#__DIR__\.'/\.\./storage/framework/maintenance\.php'#__DIR__.'/current/storage/framework/maintenance.php'#" \
    -e "s#__DIR__\.'/\.\./vendor/autoload\.php'#__DIR__.'/current/vendor/autoload.php'#" \
    -e "s#__DIR__\.'/\.\./bootstrap/app\.php'#__DIR__.'/current/bootstrap/app.php'#" \
    "${APP_DIR}/index.php"

  if grep -q "__DIR__\.'/\.\./" "${APP_DIR}/index.php"; then
    echo "ERREUR: index.php contient encore une reference '../' non adaptee - verifier public/index.php." >&2
    return 1
  fi

  rm -f "${APP_DIR}/storage"
  ln -s shared/storage/app/public "${APP_DIR}/storage"
}

if [[ ! -L "${APP_DIR}/current" ]]; then
  echo "Aucune version en service (${APP_DIR}/current absent) - retour impossible." >&2
  exit 1
fi

EN_SERVICE="$(basename "$(readlink -f "${APP_DIR}/current")")"
PRECEDENTE="$(ls -1 "${APP_DIR}/releases" | sort | awk -v courante="${EN_SERVICE}" '$0 == courante { print precedente; exit } { precedente = $0 }')"

if [[ -z "${PRECEDENTE}" ]]; then
  echo "Aucune version anterieure a ${EN_SERVICE} - retour impossible." >&2
  exit 1
fi

echo "==> Retour de ${EN_SERVICE} vers ${PRECEDENTE}"

ln -s "${APP_DIR}/releases/${PRECEDENTE}" "${APP_DIR}/current.new"
mv -Tf "${APP_DIR}/current.new" "${APP_DIR}/current"
synchroniser_public "${APP_DIR}/releases/${PRECEDENTE}"

echo "$(date -Iseconds) retour de=${EN_SERVICE} vers=${PRECEDENTE} statut=RETOUR" >> "${APP_DIR}/shared/deploy.log"

sleep 2
if curl -fsS --max-time 10 "${HEALTHCHECK_URL}" > /dev/null; then
  echo "==> Retour vers ${PRECEDENTE} reussi."
else
  echo "Retour effectue mais healthcheck toujours KO - investigation manuelle requise." >&2
  exit 1
fi

echo ""
echo "Note : ce script ne touche jamais au schema de base de donnees."
echo "Si la version abandonnee a introduit une migration incompatible,"
echo "un 'php artisan migrate:rollback' manuel et reflechi reste necessaire."
```

- [ ] **Step 3: Write `bin/deploy`**

```bash
#!/usr/bin/env bash
# Commande unique de deploiement de presence-paiement-cafab (CAFAB), lancee
# depuis la machine du developpeur : elle fait le SSH a votre place.
# Reprise de caisse-depenses/bin/deploy, avec ses propres variables.
#
# Configuration (variables d'environnement, JAMAIS versionnees - a definir
# dans votre shell local, ex. ~/.bashrc) :
#   PRESENCE_DEPLOY_HOST     utilisateur@hote (obligatoire)
#   PRESENCE_DEPLOY_PORT     port SSH (defaut : 22)
#   PRESENCE_DEPLOY_KEY      chemin de la cle privee SSH (optionnel)
#   PRESENCE_DEPLOY_APP_DIR  dossier distant, relatif a ~ (defaut : presence.fillesdartsbenin.com)
#   PRESENCE_DEPLOY_PHP_BIN  PHP 8.3 du serveur, si `php` y est plus ancien (optionnel)
#
# Usage :
#   bin/deploy              -> deploie la branche main
#   bin/deploy ma-branche   -> deploie une autre branche
#   bin/deploy rollback     -> revient a la version precedente
set -euo pipefail

if [[ -z "${PRESENCE_DEPLOY_HOST:-}" ]]; then
  echo "ERREUR: variable PRESENCE_DEPLOY_HOST non definie (ex. export PRESENCE_DEPLOY_HOST=utilisateur@serveur)." >&2
  exit 1
fi

HOST="${PRESENCE_DEPLOY_HOST}"
PORT="${PRESENCE_DEPLOY_PORT:-22}"
REMOTE_APP_DIR="${PRESENCE_DEPLOY_APP_DIR:-presence.fillesdartsbenin.com}"
ARG="${1:-main}"

# Ces valeurs sont recopiees dans la commande distante : on refuse tout
# caractere qui permettrait d'y glisser une autre commande.
SUR='^[A-Za-z0-9._/-]+$'
if [[ ! "${ARG}" =~ ${SUR} ]]; then
  echo "ERREUR: nom de branche invalide (${ARG}). Caracteres permis : lettres, chiffres, . _ / -" >&2
  exit 1
fi
if [[ ! "${REMOTE_APP_DIR}" =~ ${SUR} ]]; then
  echo "ERREUR: PRESENCE_DEPLOY_APP_DIR invalide (${REMOTE_APP_DIR})." >&2
  exit 1
fi

ENV_DISTANT=""
if [[ -n "${PRESENCE_DEPLOY_PHP_BIN:-}" ]]; then
  if [[ ! "${PRESENCE_DEPLOY_PHP_BIN}" =~ ${SUR} ]]; then
    echo "ERREUR: PRESENCE_DEPLOY_PHP_BIN invalide (${PRESENCE_DEPLOY_PHP_BIN})." >&2
    exit 1
  fi
  ENV_DISTANT="PHP_BIN='${PRESENCE_DEPLOY_PHP_BIN}' "
fi

SSH_OPTS=(-o BatchMode=yes -o ConnectTimeout=10 -p "${PORT}")
if [[ -n "${PRESENCE_DEPLOY_KEY:-}" ]]; then
  SSH_OPTS+=(-i "${PRESENCE_DEPLOY_KEY}")
fi

if [[ "${ARG}" == "rollback" ]]; then
  echo "-> Retour a la version precedente sur ${HOST}..."
  ssh "${SSH_OPTS[@]}" "${HOST}" "bash ~/${REMOTE_APP_DIR}/rollback.sh"
else
  echo "-> Deploiement de la branche '${ARG}' sur ${HOST}..."
  ssh "${SSH_OPTS[@]}" "${HOST}" "${ENV_DISTANT}BRANCH='${ARG}' bash ~/${REMOTE_APP_DIR}/deploy.sh"
fi

echo "OK termine."
```

- [ ] **Step 4: Check the syntax, and the differences against Caisse CAFAB**

Run (Git Bash, from the worktree root):

```bash
bash -n deploy/deploy-shared-hosting.sh && bash -n deploy/rollback-shared-hosting.sh && bash -n bin/deploy && echo "syntaxe OK"
command -v shellcheck && shellcheck deploy/deploy-shared-hosting.sh deploy/rollback-shared-hosting.sh bin/deploy || echo "shellcheck absent : a signaler dans le rapport"
C=/c/wamp64/www/caisse_cafab/caisse-depenses
diff -u "$C/deploy/deploy-shared-hosting.sh" deploy/deploy-shared-hosting.sh
diff -u "$C/deploy/rollback-shared-hosting.sh" deploy/rollback-shared-hosting.sh
diff -u "$C/bin/deploy" bin/deploy
```

Expected: `syntaxe OK`. Read each diff hunk and check it maps to one of the nine differences listed at the top of this task; any other difference is a mistake to fix. Report what `shellcheck` said, or that it is absent. A `shellcheck` warning SC2012 on the `ls` pipelines is acceptable (release names are timestamps without spaces); anything else gets fixed.

- [ ] **Step 5: Exercise the guards locally (Review Focus)**

These cases stop before anything is cloned or contacted, so they are safe to run on Windows Git Bash:

```bash
TMP="$(mktemp -d)"

APP_DIR="$TMP" bash deploy/deploy-shared-hosting.sh; echo "code=$?"
# attendu : "ERREUR: .../shared/.env introuvable..." puis code=1

mkdir -p "$TMP/shared" && touch "$TMP/shared/.env"
APP_DIR="$TMP" PHP_BIN=/chemin/inexistant/php bash deploy/deploy-shared-hosting.sh; echo "code=$?"
# attendu : "ERREUR: PHP introuvable (/chemin/inexistant/php)..." puis code=1

printf '#!/usr/bin/env bash\nif [[ "$2" == *PHP_VERSION_ID* ]]; then exit 1; fi\necho 8.2.29\n' > "$TMP/php82"
chmod +x "$TMP/php82"
APP_DIR="$TMP" PHP_BIN="$TMP/php82" bash deploy/deploy-shared-hosting.sh; echo "code=$?"
# attendu : "ERREUR: .../php82 est en PHP 8.2.29, il faut PHP 8.3 ou plus..." puis code=1

ls "$TMP"            # attendu : seulement php82 et shared (rien de clone)

env -u PRESENCE_DEPLOY_HOST bash bin/deploy; echo "code=$?"
# attendu : "ERREUR: variable PRESENCE_DEPLOY_HOST non definie..." puis code=1

PRESENCE_DEPLOY_HOST=personne@exemple.invalid bash bin/deploy 'main;id'; echo "code=$?"
# attendu : "ERREUR: nom de branche invalide (main;id)..." puis code=1

PRESENCE_DEPLOY_HOST=personne@exemple.invalid PRESENCE_DEPLOY_PHP_BIN='php;id' bash bin/deploy; echo "code=$?"
# attendu : "ERREUR: PRESENCE_DEPLOY_PHP_BIN invalide (php;id)." puis code=1

rm -rf "$TMP"
```

Expected: each case prints its message and `code=1`, and no `ssh` or `git clone` is attempted. Paste the outputs into the report.

- [ ] **Step 6: Commit with the executable bit**

```bash
git add --chmod=+x deploy/deploy-shared-hosting.sh deploy/rollback-shared-hosting.sh bin/deploy
git commit -m "feat(deploy): scripts de déploiement et de retour arrière pour cPanel

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
git ls-files -s deploy bin
```

Expected: the last command shows mode `100755` for the three files.

---

### Task 4: Production configuration, README and composer.lock

**Files:**
- Create: `.env.production.example`
- Modify: `README.md` (sections Sommaire, Configuration, Tâches planifiées, new Déploiement, Documentation, Statut du projet)
- Modify: `composer.lock` (content hash only)

**Interfaces:**
- Consumes: Task 3's script names, variables and server layout (see Task 3 Interfaces).
- Produces: nothing used by code.

- [ ] **Step 1: Write `.env.production.example`**

It is not ignored by `.gitignore` (only `.env`, `.env.backup` and `.env.production` are). Content:

```dotenv
# Modele du .env de production. Sur le serveur, le copier en
# ~/presence.fillesdartsbenin.com/shared/.env puis remplir les valeurs vides.
# Ne jamais versionner le fichier rempli.

APP_NAME="Présence & Paiements CAFAB"
APP_ENV=production
# Generee automatiquement au premier deploiement (php artisan key:generate).
APP_KEY=
APP_DEBUG=false
APP_URL=https://presence.fillesdartsbenin.com
APP_TIMEZONE=Africa/Porto-Novo

APP_LOCALE=fr
APP_FALLBACK_LOCALE=en

# Demarre, cloture et genere les seances au passage des pages (au plus une fois
# par minute), en plus du cron du planificateur. Laisser a true.
SEANCES_SYNCHRONISATION_AUTO=true
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

# Journaux quotidiens gardes 30 jours : ils contiennent des adresses IP.
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DAILY_DAYS=30
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

# Base MySQL dediee creee dans cPanel (jamais celle de Caisse CAFAB). cPanel
# prefixe le nom de la base et de l'utilisateur par celui du compte.
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

# Les sessions en base permettent de deconnecter les autres appareils apres un
# changement de mot de passe.
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=database

# Boite noreply@fillesdartsbenin.com creee dans cPanel. Serveur et port a
# confirmer dans cPanel, Comptes de messagerie, Connecter les appareils.
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.fillesdartsbenin.com
MAIL_PORT=465
MAIL_USERNAME=noreply@fillesdartsbenin.com
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="noreply@fillesdartsbenin.com"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"

# Liaison avec Caisse CAFAB : meme valeur que PRESENCE_PAIEMENT_CAFAB_SERVICE_TOKEN
# dans le .env de production de Caisse CAFAB (openssl rand -hex 32).
CAISSE_CAFAB_API_URL=https://caisse.fillesdartsbenin.com
CAISSE_CAFAB_API_TOKEN=
```

- [ ] **Step 2: Update the README**

In the Sommaire, after `- [Tâches planifiées](#tâches-planifiées)`, add:

```markdown
- [Déploiement](#déploiement)
```

In section « Configuration », after the paragraph that starts « En production, le jeton de service et l'URL de l'API… », add:

```markdown
En production, partir du modèle `.env.production.example` (voir [Déploiement](#déploiement)).
```

In section « Tâches planifiées », replace the first paragraph (« Le cycle de vie des séances dépend du planificateur Laravel — … ») with:

```markdown
Le cycle de vie des séances passe par le planificateur Laravel. En production, une tâche cron le lance chaque minute (ligne exacte dans [Déploiement](#déploiement)) ; en développement, `php artisan schedule:work`. Les pages de l'application font aussi avancer les séances, au plus une fois par minute (`SEANCES_SYNCHRONISATION_AUTO=true`) : le cron reste le filet de sécurité quand personne n'ouvre l'application.
```

Insert this new section between « Tâches planifiées » and « Tests »:

````markdown
## Déploiement

Production : <https://presence.fillesdartsbenin.com>, sur le même hébergement mutualisé cPanel que Caisse CAFAB. Le principe est le même : chaque déploiement crée une version dans `releases/`, bascule dessus d'un coup, puis vérifie `/up`. Si la vérification échoue, le site revient seul à la version qui était en service.

| Fichier | Rôle |
|---|---|
| `bin/deploy` | Se lance depuis votre machine : se connecte en SSH et exécute le script distant |
| `deploy/deploy-shared-hosting.sh` | Copié sur le serveur en `deploy.sh` : déploie une branche |
| `deploy/rollback-shared-hosting.sh` | Copié sur le serveur en `rollback.sh` : revient à la version précédente |

Arborescence sur le serveur :

```text
~/presence.fillesdartsbenin.com/     racine du sous-domaine
├── releases/<AAAAMMJJHHMMSS>/       une version par déploiement, 5 conservées
├── shared/.env                       configuration de production
├── shared/storage/                   fichiers et journaux, communs à toutes les versions
├── shared/deploy.log                 journal des déploiements
├── current -> releases/<…>          version en service
└── deploy.sh, rollback.sh, index.php et le contenu de public/
```

### Commandes

```bash
export PRESENCE_DEPLOY_HOST=utilisateur@serveur                      # obligatoire
export PRESENCE_DEPLOY_PORT=22                                       # optionnel
export PRESENCE_DEPLOY_KEY="$HOME/.ssh/ma_cle"                        # optionnel
export PRESENCE_DEPLOY_PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php  # si le `php` du serveur est antérieur à 8.3

bin/deploy              # déploie la branche main
bin/deploy ma-branche   # déploie une autre branche
bin/deploy rollback     # revient à la version précédente
```

Ces variables restent dans votre shell (par exemple `~/.bashrc`) et ne sont jamais versionnées.

### Mise en ligne, la première fois

1. **Caisse CAFAB d'abord.** Générer un jeton (`openssl rand -hex 32`), l'ajouter comme `PRESENCE_PAIEMENT_CAFAB_SERVICE_TOKEN` dans le `shared/.env` de Caisse CAFAB, puis la redéployer depuis `main` avec son propre `bin/deploy` (sa migration `external_reference` passe à cette occasion).
2. **cPanel.** Créer le sous-domaine `presence.fillesdartsbenin.com` avec pour racine `~/presence.fillesdartsbenin.com`, une base MySQL et son utilisateur (jamais la base de Caisse CAFAB), la boîte `noreply@fillesdartsbenin.com`, le certificat AutoSSL, et choisir PHP 8.3 pour le sous-domaine.
3. **Serveur (SSH).** Créer l'arborescence :

   ```bash
   mkdir -p ~/presence.fillesdartsbenin.com/{releases,shared/storage/app/public,shared/storage/framework/cache/data,shared/storage/framework/sessions,shared/storage/framework/views,shared/storage/logs}
   ```

   Depuis votre machine, copier le modèle de configuration et, une première fois, les scripts (ensuite, chaque déploiement met les scripts à jour) :

   ```bash
   scp -P 22 .env.production.example utilisateur@serveur:presence.fillesdartsbenin.com/shared/.env
   scp -P 22 deploy/deploy-shared-hosting.sh utilisateur@serveur:presence.fillesdartsbenin.com/deploy.sh
   scp -P 22 deploy/rollback-shared-hosting.sh utilisateur@serveur:presence.fillesdartsbenin.com/rollback.sh
   ```

   Sur le serveur, remplir dans `shared/.env` les valeurs `DB_*`, `MAIL_PASSWORD` et `CAISSE_CAFAB_API_TOKEN` (même jeton qu'à l'étape 1), puis `chmod 600 ~/presence.fillesdartsbenin.com/shared/.env`. Si le dépôt GitHub est privé, créer sur le serveur une clé de déploiement en lecture seule (`ssh-keygen`, puis `gh repo deploy-key add` ou l'interface GitHub) et la déclarer pour `github.com` dans `~/.ssh/config`.

4. **Publication.** Fusionner `develop` dans `main` par une pull request, puis lancer `bin/deploy`.
5. **Cron.** Ajouter dans cPanel, « Tâches Cron », la ligne affichée par le script, de la forme :
   `* * * * * cd /home/<compte>/presence.fillesdartsbenin.com/current && php artisan schedule:run >> /dev/null 2>&1`
   (remplacer `php` par le chemin de PHP 8.3 si vous avez réglé `PRESENCE_DEPLOY_PHP_BIN`).
6. **Premier admin.** `cd ~/presence.fillesdartsbenin.com/current && php artisan users:create "Nom Complet" email@exemple.com --role=admin` ; le mot de passe provisoire s'affiche une seule fois.
7. **Vérifications.** Connexion, email « mot de passe oublié », kiosque sur la tablette. La liaison avec Caisse CAFAB se vérifie sur le premier vrai cachet validé, pour ne pas créer de fausse dépense dans la comptabilité.

### Retour arrière

`bin/deploy rollback` revient à la version qui précède celle en service ; deux appels de suite reculent de deux versions. Le schéma de la base n'est jamais modifié : si la version abandonnée a introduit une migration incompatible, un `php artisan migrate:rollback` réfléchi, lancé à la main dans `current/`, reste nécessaire.
````

In section « Documentation », after the line `4. \`2026-09-23-rapports.md\` — rapports de ponctualité et de dépenses`, add:

```markdown
5. `2026-09-26-corrections.md` — corrections après les premiers tests
6. `2026-09-26-deploiement.md` — mise en production
```

In section « Statut du projet », replace the first paragraph (« Les quatre incréments prévus par la note de cadrage sont fusionnés dans `develop`. … ») with:

```markdown
Les quatre incréments prévus par la note de cadrage sont fusionnés dans `develop`, ainsi que la refonte visuelle, les corrections issues des premiers tests et la préparation de la mise en production. Chaque incrément est passé par une revue par tâche puis une revue finale de branche entière avant fusion.
```

- [ ] **Step 3: Refresh the composer.lock hash**

Run: `composer update --lock` then `composer validate --no-check-publish` and `git diff --stat composer.lock`.
Expected: `./composer.json is valid` with no lock-file error, and a diff limited to the `content-hash` line. If any package version changes in the diff, stop: run `git checkout -- composer.lock` and report it instead.

- [ ] **Step 4: Full verification on SQLite and MySQL**

Run: `npm run build`, `php artisan test` (all green) and `vendor/bin/pint --test`.

Then the same suite on a throwaway MySQL 8.4 from WAMP (its own data directory in the session scratchpad and its own port, WAMP's data untouched):

```bash
B="C:/wamp64/bin/mysql/mysql8.4.7"; SP="C:/Users/jenmf/AppData/Local/Temp/claude/c--wamp64-www-caisse-cafab/8d67af7d-3e57-4de9-b44a-bd55f3952e1b/scratchpad"
"$B/bin/mysqld.exe" --no-defaults --basedir="$B" --datadir="$SP/mysqldata" --initialize-insecure --console
"$B/bin/mysqld.exe" --no-defaults --basedir="$B" --datadir="$SP/mysqldata" --port=3399 --bind-address=127.0.0.1 --mysqlx=OFF --console &   # en arrière-plan
"$B/bin/mysql.exe" -h127.0.0.1 -P3399 -uroot -e "CREATE DATABASE cafab_verif CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3399 DB_DATABASE=cafab_verif DB_USERNAME=root DB_PASSWORD= php artisan test
"$B/bin/mysqladmin.exe" -h127.0.0.1 -P3399 -uroot shutdown
rm -rf "$SP/mysqldata"
```

Expected: all green, with exactly one test skipped ("aborts without changing anything when two accounts would end up with the same email", skipped outside SQLite on purpose).

- [ ] **Step 5: Commit**

```bash
git checkout -- package-lock.json 2>/dev/null
git add .env.production.example README.md composer.lock
git commit -m "docs: configuration de production et procédure de mise en ligne

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
```
