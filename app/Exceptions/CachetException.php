<?php

namespace App\Exceptions;

use RuntimeException;

class CachetException extends RuntimeException
{
    public static function nonEligible(): self
    {
        return new self('Ce cachet ne peut plus être déclaré.');
    }

    public static function prestationNonEncorePassee(): self
    {
        return new self("Cette prestation n'a pas encore eu lieu.");
    }
}
