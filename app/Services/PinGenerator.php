<?php

namespace App\Services;

use App\Models\Coach;
use App\Models\Fille;

class PinGenerator
{
    public function generate(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while ($this->isTaken($pin));

        return $pin;
    }

    public function isTaken(string $pin): bool
    {
        return Coach::where('pin', $pin)->exists() || Fille::where('pin', $pin)->exists();
    }
}
