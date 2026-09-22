<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutPonctualite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CorrigerPointageRequest;
use App\Models\Pointage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PointageController extends Controller
{
    public function index(Request $request): View
    {
        $query = Pointage::with(['seance', 'pointable']);

        if ($request->filled('pointable_type') && $request->filled('pointable_id')) {
            $query->where('pointable_type', $request->string('pointable_type'))
                ->where('pointable_id', $request->integer('pointable_id'));
        }

        $pointages = $query->orderByDesc('pointe_a')->paginate(30);

        return view('admin.pointages.index', compact('pointages'));
    }

    public function corriger(CorrigerPointageRequest $request, Pointage $pointage): RedirectResponse
    {
        $statut = StatutPonctualite::from($request->validated('statut_ponctualite'));

        $minutesRetard = match ($statut) {
            StatutPonctualite::ALHeure, StatutPonctualite::Absent => 0,
            StatutPonctualite::EnRetard, StatutPonctualite::RetardFort => $request->filled('minutes_retard')
                ? $request->validated('minutes_retard')
                : $pointage->minutes_retard,
        };

        $pointage->update([
            'statut_ponctualite' => $statut,
            'minutes_retard' => $minutesRetard,
            'corrige_par_user_id' => $request->user()->id,
            'motif_correction' => $request->validated('motif'),
        ]);

        return redirect()->route('admin.pointages.index')->with('message', 'Pointage corrigé.');
    }
}
