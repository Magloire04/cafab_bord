<?php

namespace App\Http\Controllers;

use App\Enums\TypeSeance;
use App\Http\Requests\StoreSeanceExtraordinaireRequest;
use App\Models\Coach;
use App\Models\Seance;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SeanceController extends Controller
{
    public function create(): View
    {
        $coaches = Coach::with('user')->where('statut', 'actif')->get();

        return view('seances.create-extraordinaire', compact('coaches'));
    }

    public function store(StoreSeanceExtraordinaireRequest $request): RedirectResponse
    {
        Seance::create([
            'planning_repetition_id' => null,
            'coach_id' => $request->validated('coach_id'),
            'date' => $request->validated('date'),
            'heure_prevue' => $request->validated('heure_prevue'),
            'type' => TypeSeance::Extraordinaire,
            'statut' => 'a_venir',
        ]);

        return redirect()->route('dashboard')->with('message', 'Séance extraordinaire créée.');
    }
}
