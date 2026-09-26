<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un coach désactivé par l'admin alors qu'il était connecté perd sa session
 * à sa requête suivante, sur n'importe quelle page (tableau de bord et profil
 * compris, qui ne passent pas par le middleware « role »).
 */
class DeconnecterCoachDesactive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->estCoachDesactive()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(403, __('auth.inactive'));
            }

            return redirect()->route('login')->withErrors(['email' => __('auth.inactive')]);
        }

        return $next($request);
    }
}
