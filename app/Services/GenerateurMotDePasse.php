<?php

namespace App\Services;

use Illuminate\Validation\Rules\Password;

/**
 * Mots de passe provisoires transmis par l'admin : 10 caractères avec au
 * moins une majuscule, une minuscule, un chiffre et un caractère spécial.
 * Les caractères ambigus à la lecture (0/O, 1/l/I) sont exclus. Les mêmes
 * jeux sont repris dans resources/js/mot-de-passe-provisoire.js.
 */
class GenerateurMotDePasse
{
    private const JEUX = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnopqrstuvwxyz',
        '23456789',
        '!@#$%&*?-_+=',
    ];

    public static function regle(): Password
    {
        return Password::min(10)->letters()->mixedCase()->numbers()->symbols();
    }

    public function generer(int $longueur = 10): string
    {
        $tous = implode('', self::JEUX);
        $caracteres = array_map(fn (string $jeu) => $jeu[random_int(0, strlen($jeu) - 1)], self::JEUX);

        while (count($caracteres) < $longueur) {
            $caracteres[] = $tous[random_int(0, strlen($tous) - 1)];
        }

        // Mélange de Fisher-Yates avec random_int : shuffle() n'est pas un aléa cryptographique.
        for ($i = count($caracteres) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
        }

        return implode('', $caracteres);
    }
}
