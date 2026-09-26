<?php

namespace App\Enums;

enum StatutSeance: string
{
    case AVenir = 'a_venir';
    case EnCours = 'en_cours';
    case Cloturee = 'cloturee';

    /** Libellé affiché à l'écran. */
    public function libelle(): string
    {
        return match ($this) {
            self::AVenir => 'À venir',
            self::EnCours => 'En cours',
            self::Cloturee => 'Clôturée',
        };
    }
}
