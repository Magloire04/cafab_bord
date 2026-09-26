<?php

namespace App\Http\Controllers\Kiosque;

use App\Http\Controllers\Controller;
use App\Services\EtatSeances;
use Illuminate\Http\JsonResponse;

class EtatController extends Controller
{
    public function __invoke(EtatSeances $etat): JsonResponse
    {
        return response()->json($etat->pourKiosque());
    }
}
