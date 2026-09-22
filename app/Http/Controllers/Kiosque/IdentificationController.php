<?php

namespace App\Http\Controllers\Kiosque;

use App\Http\Controllers\Controller;
use App\Models\Fille;
use App\Models\Seance;
use App\Services\KioskIdentifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IdentificationController extends Controller
{
    public function home(): View
    {
        $seance = Seance::where('statut', 'en_cours')->latest('heure_prevue')->first();

        return view('kiosque.accueil', compact('seance'));
    }

    public function identifier(Request $request, KioskIdentifier $identifier): RedirectResponse
    {
        $request->validate(['pin' => ['required', 'string', 'size:4']]);

        $personne = $identifier->identifier($request->string('pin')->value());

        if (! $personne) {
            return redirect()->route('kiosque.home')->withErrors(['pin' => 'Code inconnu.']);
        }

        $request->session()->put('kiosque.identifie', [
            'type' => $personne::class,
            'id' => $personne->id,
        ]);

        $nomComplet = $personne instanceof Fille
            ? "{$personne->prenom} {$personne->nom}"
            : $personne->user->name;

        $request->session()->put('kiosque.nom', $nomComplet);

        return redirect()->route('kiosque.menu');
    }

    public function menu(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('kiosque.identifie')) {
            return redirect()->route('kiosque.home');
        }

        return view('kiosque.menu', ['nom' => $request->session()->get('kiosque.nom')]);
    }
}
