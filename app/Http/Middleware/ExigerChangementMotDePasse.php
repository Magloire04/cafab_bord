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
