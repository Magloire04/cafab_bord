<?php

namespace App\Services;

use App\Models\Coach;
use App\Models\Fille;
use Illuminate\Database\Eloquent\Model;

class KioskIdentifier
{
    public function identifier(string $pin): ?Model
    {
        return Fille::where('pin', $pin)->first()
            ?? Coach::where('pin', $pin)->first();
    }
}
