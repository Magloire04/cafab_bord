<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seul point d'écriture d'un mot de passe : profil, lien de réinitialisation,
 * changement obligatoire, réinitialisation par l'admin et commande artisan.
 */
class ChangementMotDePasse
{
    /**
     * Mot de passe choisi par l'utilisateur : ses autres sessions sont fermées.
     */
    public function definir(User $user, string $motDePasse, ?string $sessionAConserver = null): void
    {
        $user->forceFill([
            'password' => Hash::make($motDePasse),
            'remember_token' => Str::random(60),
            'must_change_password' => false,
        ])->save();

        $this->fermerSessions($user, $sessionAConserver);
    }

    /**
     * Même chose pour l'utilisateur connecté, qui reste connecté sur ce navigateur.
     */
    public function definirPourSessionCourante(Request $request, string $motDePasse): void
    {
        $user = $request->user();
        $avaitCookieDeRappel = $request->cookies->has(Auth::guard()->getRecallerName());

        $this->definir($user, $motDePasse, $request->session()->getId());

        // Le jeton « se souvenir de moi » vient de changer : on réémet le cookie
        // de ce navigateur pour qu'il reste reconnu, les autres le perdent.
        if ($avaitCookieDeRappel) {
            Auth::guard()->login($user, true);
        }
    }

    /**
     * Mot de passe provisoire posé par l'admin ou la commande : toutes les sessions tombent.
     */
    public function imposerProvisoire(User $user, string $motDePasse): void
    {
        $user->forceFill([
            'password' => Hash::make($motDePasse),
            'remember_token' => Str::random(60),
            'must_change_password' => true,
        ])->save();

        $this->fermerSessions($user, null);
    }

    private function fermerSessions(User $user, ?string $sessionAConserver): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->when($sessionAConserver !== null, fn ($query) => $query->where('id', '!=', $sessionAConserver))
            ->delete();
    }
}
