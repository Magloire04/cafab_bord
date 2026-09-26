<?php

use App\Http\Middleware\DeconnecterCoachDesactive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ExigerChangementMotDePasse;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SynchroniserSeances;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
