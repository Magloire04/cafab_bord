<?php

namespace App\Http\Controllers\Kiosque;

use App\Enums\SourcePointage;
use App\Exceptions\PointageException;
use App\Http\Controllers\Controller;
use App\Models\Fille;
use App\Models\Seance;
use App\Services\PointageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PointageController extends Controller
{
    public function store(Request $request, PointageService $pointageService): View|RedirectResponse
    {
        $identifie = $request->session()->get('kiosque.identifie');

        if (! $identifie) {
            return redirect()->route('kiosque.home');
        }

        $personne = $identifie['type']::find($identifie['id']);
        $seance = Seance::where('statut', 'en_cours')->latest('heure_prevue')->first();

        if (! $seance) {
            return view('kiosque.confirmation', [
                'nom' => $request->session()->get('kiosque.nom'),
                'erreur' => 'Aucune répétition en cours pour le moment.',
            ]);
        }

        try {
            $pointage = $pointageService->pointer($seance, $personne, Carbon::now(), SourcePointage::Auto);
        } catch (PointageException $e) {
            return view('kiosque.confirmation', [
                'nom' => $request->session()->get('kiosque.nom'),
                'erreur' => $e->getMessage(),
            ]);
        }

        $request->session()->forget(['kiosque.identifie', 'kiosque.nom']);

        return view('kiosque.confirmation', [
            'nom' => $identifie['type'] === Fille::class ? "{$personne->prenom}" : $personne->user->name,
            'pointage' => $pointage,
        ]);
    }
}
