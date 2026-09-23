<?php

namespace App\Http\Controllers\Kiosque;

use App\Enums\StatutCachet;
use App\Enums\StatutPrestation;
use App\Exceptions\CachetException;
use App\Http\Controllers\Controller;
use App\Models\Cachet;
use App\Models\Fille;
use App\Services\CachetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CachetController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $identifie = $request->session()->get('kiosque.identifie');

        if (! $identifie || $identifie['type'] !== Fille::class) {
            return redirect()->route('kiosque.menu');
        }

        $fille = Fille::findOrFail($identifie['id']);

        $cachets = Cachet::where('fille_id', $fille->id)
            ->whereIn('statut', [StatutCachet::Du, StatutCachet::DeclareePayee, StatutCachet::DeclareeNonPayee])
            ->whereHas('prestation', fn ($q) => $q->where('statut', StatutPrestation::Active)->where('date', '<', today()))
            ->with('prestation')
            ->get();

        return view('kiosque.cachets.index', [
            'nom' => $request->session()->get('kiosque.nom'),
            'cachets' => $cachets,
        ]);
    }

    public function declarer(Request $request, Cachet $cachet, CachetService $service): View
    {
        $identifie = $request->session()->get('kiosque.identifie');

        abort_if(! $identifie || $identifie['type'] !== Fille::class || $identifie['id'] !== $cachet->fille_id, 403);

        $request->validate(['recu' => ['required', 'boolean']]);

        $nom = $request->session()->get('kiosque.nom');
        $request->session()->forget(['kiosque.identifie', 'kiosque.nom']);

        try {
            $service->declarer($cachet, $request->boolean('recu'));
        } catch (CachetException $e) {
            return view('kiosque.cachets.confirmation', ['nom' => $nom, 'erreur' => $e->getMessage()]);
        }

        return view('kiosque.cachets.confirmation', ['nom' => $nom]);
    }
}
