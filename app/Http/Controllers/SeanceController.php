<?php

namespace App\Http\Controllers;

use App\Enums\TypeSeance;
use App\Enums\UserRole;
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
        // A coach (unlike an admin) may only ever create a séance
        // extraordinaire for themselves — otherwise a coach could submit
        // someone else's coach_id and attribute the whole evening's
        // pointages to a coach who isn't even present.
        if ($request->user()->role === UserRole::Admin) {
            $coachId = $request->validated('coach_id');
        } else {
            $coachId = $request->user()->coach?->id;
            abort_if($coachId === null, 403);
        }

        Seance::create([
            'planning_repetition_id' => null,
            'coach_id' => $coachId,
            'date' => $request->validated('date'),
            'heure_prevue' => $request->validated('heure_prevue'),
            'type' => TypeSeance::Extraordinaire,
            'statut' => 'a_venir',
        ]);

        return redirect()->route('dashboard')->with('message', 'Séance extraordinaire créée.');
    }
}
