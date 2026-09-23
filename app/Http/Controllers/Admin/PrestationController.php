<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutCachet;
use App\Enums\StatutPrestation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePrestationRequest;
use App\Models\Cachet;
use App\Models\Fille;
use App\Models\Prestation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PrestationController extends Controller
{
    public function index(): View
    {
        $prestations = Prestation::withCount('cachets')->orderByDesc('date')->paginate(20);

        return view('admin.prestations.index', compact('prestations'));
    }

    public function create(): View
    {
        $filles = Fille::where('statut', 'actif')->orderBy('nom')->get();

        return view('admin.prestations.create', compact('filles'));
    }

    public function store(StorePrestationRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $prestation = Prestation::create([
                'titre' => $request->validated('titre'),
                'lieu' => $request->validated('lieu'),
                'date' => $request->validated('date'),
                'montant_defaut' => $request->validated('montant_defaut'),
            ]);

            $montants = $request->validated('montants') ?? [];

            foreach ($request->validated('fille_ids') as $filleId) {
                Cachet::create([
                    'prestation_id' => $prestation->id,
                    'fille_id' => $filleId,
                    'montant' => $montants[$filleId] ?? $request->validated('montant_defaut'),
                    'statut' => StatutCachet::Du,
                ]);
            }
        });

        return redirect()->route('admin.prestations.index')->with('message', 'Prestation créée.');
    }

    public function show(Prestation $prestation): View
    {
        $prestation->load(['cachets.fille', 'cachets.valideParUser', 'cachets.corrigeParUser']);

        return view('admin.prestations.show', compact('prestation'));
    }

    public function annuler(Prestation $prestation): RedirectResponse
    {
        $prestation->update(['statut' => StatutPrestation::Annulee]);

        $prestation->cachets()
            ->whereNotIn('statut', [StatutCachet::ValideePayee, StatutCachet::Annule])
            ->update(['statut' => StatutCachet::Annule]);

        return redirect()->route('admin.prestations.index')->with('message', 'Prestation annulée.');
    }
}
