<?php

namespace App\Http\Controllers\Coach;

use App\Enums\SourcePointage;
use App\Exceptions\PointageException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coach\MarquerPresenteRequest;
use App\Models\Coach;
use App\Models\Fille;
use App\Models\Pointage;
use App\Models\Seance;
use App\Services\PointageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PointageController extends Controller
{
    public function show(Request $request): View
    {
        $coach = $request->user()->coach;

        $seance = Seance::where('coach_id', $coach?->id)
            ->where('statut', 'en_cours')
            ->whereDate('date', today())
            ->first()
            ?? Seance::where('coach_id', $coach?->id)
                ->where('statut', 'a_venir')
                ->whereDate('date', '>=', today())
                ->orderBy('date')
                ->orderBy('heure_prevue')
                ->first();

        $pointages = $seance ? $seance->pointages()->with('pointable')->get() : collect();
        $filles = Fille::where('statut', 'actif')->orderBy('nom')->get();

        return view('coach.seance', compact('seance', 'pointages', 'filles'));
    }

    public function marquerPresente(MarquerPresenteRequest $request, PointageService $pointageService): RedirectResponse
    {
        $seance = Seance::findOrFail($request->validated('seance_id'));
        abort_if($seance->coach_id !== $request->user()->coach?->id, 403);

        $personne = $request->validated('pointable_type')::findOrFail($request->validated('pointable_id'));

        $heure = $request->filled('heure')
            ? Carbon::parse($seance->date->format('Y-m-d').' '.$request->validated('heure'))
            : Carbon::now();

        try {
            $pointageService->pointer($seance, $personne, $heure, SourcePointage::Coach, $request->user());
        } catch (PointageException $e) {
            return back()->withErrors(['pointage' => $e->getMessage()]);
        }

        return back()->with('message', 'Présence enregistrée.');
    }

    public function cloturer(Request $request): RedirectResponse
    {
        $seance = Seance::findOrFail($request->input('seance_id'));
        abort_if($seance->coach_id !== $request->user()->coach?->id, 403);

        $seance->clore($request->user());

        return redirect()->route('coach.seance')->with('message', 'Séance clôturée.');
    }

    public function historique(Request $request): View
    {
        $coach = $request->user()->coach;

        $pointages = Pointage::where('pointable_type', Coach::class)
            ->where('pointable_id', $coach?->id)
            ->with('seance')
            ->orderByDesc('pointe_a')
            ->paginate(30);

        return view('coach.historique', compact('pointages'));
    }
}
