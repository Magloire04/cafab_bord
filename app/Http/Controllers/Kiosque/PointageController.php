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

        if (! $personne) {
            $request->session()->forget(['kiosque.identifie', 'kiosque.nom']);

            return redirect()->route('kiosque.home');
        }

        // The kiosk session (identifie/nom) belongs to a single tap: once we
        // know who we're pointing for, clear it up front so it never lingers
        // past this request — on the success path below as well as either
        // error path — regardless of whether the client-side redirect timer
        // in the confirmation view ever fires.
        $nom = $request->session()->get('kiosque.nom');
        $request->session()->forget(['kiosque.identifie', 'kiosque.nom']);

        $seance = Seance::where('statut', 'en_cours')->whereDate('date', today())->latest('heure_prevue')->first();

        if (! $seance) {
            return view('kiosque.confirmation', [
                'nom' => $nom,
                'erreur' => 'Aucune répétition en cours pour le moment.',
            ]);
        }

        try {
            $pointage = $pointageService->pointer($seance, $personne, Carbon::now(), SourcePointage::Auto);
        } catch (PointageException $e) {
            return view('kiosque.confirmation', [
                'nom' => $nom,
                'erreur' => $e->getMessage(),
            ]);
        }

        return view('kiosque.confirmation', [
            'nom' => $identifie['type'] === Fille::class ? "{$personne->prenom}" : $personne->user->name,
            'pointage' => $pointage,
        ]);
    }
}
