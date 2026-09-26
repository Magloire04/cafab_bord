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
