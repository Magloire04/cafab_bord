<?php

namespace App\Http\Controllers\Kiosque;

use App\Http\Controllers\Controller;
use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Seance;
use App\Services\EtatSeances;
use App\Services\KioskIdentifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IdentificationController extends Controller
{
    public function home(EtatSeances $etat): View
    {
        $seance = Seance::where('statut', 'en_cours')->whereDate('date', today())->latest('heure_prevue')->first();

        return view('kiosque.accueil', [
            'seance' => $seance,
            'prochaine' => $seance ? null : $etat->pourKiosque()['prochaine'],
        ]);
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
        $identifie = $request->session()->get('kiosque.identifie');

        if (! $identifie) {
            return redirect()->route('kiosque.home');
        }

        $cachetsEnAttente = $identifie['type'] === Fille::class
            ? Cachet::eligiblesDeclaration($identifie['id'])->count()
            : 0;

        return view('kiosque.menu', [
            'nom' => $request->session()->get('kiosque.nom'),
            'cachetsEligibles' => $cachetsEnAttente > 0,
            'cachetsEnAttente' => $cachetsEnAttente,
        ]);
    }
}
