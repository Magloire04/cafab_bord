<?php

namespace App\Enums;

enum StatutSeance: string
{
    case AVenir = 'a_venir';
    case EnCours = 'en_cours';
    case Cloturee = 'cloturee';
}
