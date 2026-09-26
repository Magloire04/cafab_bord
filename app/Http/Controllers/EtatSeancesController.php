<?php

namespace App\Http\Controllers;

use App\Services\EtatSeances;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EtatSeancesController extends Controller
{
    public function __invoke(Request $request, EtatSeances $etat): JsonResponse
    {
        return response()->json($etat->pourUtilisateur($request->user()));
    }
}
