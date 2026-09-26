<?php

namespace App\Enums;

enum StatutPonctualite: string
{
    case ALHeure = 'a_l_heure';
    case EnRetard = 'en_retard';
    case Absent = 'absent';
}
