<?php

namespace App\Exceptions;

use RuntimeException;

class PointageException extends RuntimeException
{
    public static function seanceNonOuverte(): self
    {
        return new self("Cette séance n'est pas en cours : le pointage n'est pas possible.");
    }

    public static function dejaPointe(): self
    {
        return new self('Cette personne a déjà été pointée pour cette séance.');
    }
}
