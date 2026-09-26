<?php

use App\Http\Middleware\DeconnecterCoachDesactive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ExigerChangementMotDePasse;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SynchroniserSeances;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->web(append: [
            SynchroniserSeances::class,
            // Avant ExigerChangementMotDePasse : un coach désactivé est déconnecté,
            // pas envoyé vers l'écran de changement de mot de passe.
            DeconnecterCoachDesactive::class,
            ExigerChangementMotDePasse::class,
        ]);

        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
