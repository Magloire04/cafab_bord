<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Toute adresse e-mail qui entre dans l'application (connexion, mot de passe
 * oublié, réinitialisation, profil, fiche coach, commandes artisan) passe
 * par ici avant la validation et la recherche du compte.
 */
final class Email
{
    /**
     * Supprime les espaces autour et passe en minuscules. Une valeur qui n'est
     * pas une chaîne (tableau envoyé par un formulaire trafiqué, null) est
     * rendue telle quelle : les règles `string` / `email` la refusent ensuite
     * avec une erreur de validation normale.
     */
    public static function normaliser(mixed $valeur): mixed
    {
        return is_string($valeur) ? Str::lower(trim($valeur)) : $valeur;
    }
}
