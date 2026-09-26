<?php

namespace App\Http\Controllers\Kiosque;

use App\Http\Controllers\Controller;
use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Seance;
use App\Services\EtatSeances;
use App\Services\KioskIdentifier;
use App\Services\VerrouKiosque;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class IdentificationController extends Controller
{
    public function home(EtatSeances $etat): View
    {
        // Un seul calcul pour la page et pour ses attributs data-* : le script de
        // rafraîchissement compare ces identifiants à ceux de kiosque.etat, qui
        // renvoie les mêmes. S'ils différaient, le kiosque se rechargerait en boucle.
        $etatKiosque = $etat->pourKiosque();

        return view('kiosque.accueil', [
            'seance' => $etatKiosque['en_cours_id'] ? Seance::with('coach.user')->find($etatKiosque['en_cours_id']) : null,
            'prochaine' => $etatKiosque['prochaine'],
        ]);
    }

    public function identifier(Request $request, KioskIdentifier $identifier, VerrouKiosque $verrou): RedirectResponse
    {
        $ip = (string) $request->ip();

        if ($verrou->estBloque($ip)) {
            return redirect()->route('kiosque.home')->withErrors(['pin' => $verrou->messageBlocage($ip)]);
        }

        // Pas de $request->validate() : il renverrait avant le compteur, alors
        // qu'un code au mauvais format compte aussi comme un code faux.
        $validation = Validator::make($request->only('pin'), ['pin' => ['required', 'string', 'size:4']]);
        $personne = $validation->fails() ? null : $identifier->identifier($request->string('pin')->value());

        if (! $personne) {
            $verrou->enregistrerEchec($ip);

            $message = match (true) {
                $verrou->estBloque($ip) => $verrou->messageBlocage($ip),
                $validation->fails() => $validation->errors()->first('pin'),
                default => 'Code inconnu.',
            };

            return redirect()->route('kiosque.home')->withErrors(['pin' => $message]);
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
