<?php

namespace App\Http\Controllers\Kiosque;

use App\Enums\StatutCachet;
use App\Enums\StatutPrestation;
use App\Http\Controllers\Controller;
use App\Models\Cachet;
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
        $seance = Seance::where('statut', 'en_cours')->whereDate('date', today())->latest('heure_prevue')->first();

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
        $identifie = $request->session()->get('kiosque.identifie');

        if (! $identifie) {
            return redirect()->route('kiosque.home');
        }

        $cachetsEligibles = $identifie['type'] === Fille::class && Cachet::where('fille_id', $identifie['id'])
            ->whereIn('statut', [StatutCachet::Du, StatutCachet::DeclareePayee, StatutCachet::DeclareeNonPayee])
            ->whereHas('prestation', fn ($q) => $q->where('statut', StatutPrestation::Active)->where('date', '<', today()))
            ->exists();

        return view('kiosque.menu', [
            'nom' => $request->session()->get('kiosque.nom'),
            'cachetsEligibles' => $cachetsEligibles,
        ]);
    }
}
