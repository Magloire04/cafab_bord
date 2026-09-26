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
