<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutCachet;
use App\Http\Controllers\Controller;
use App\Models\Fille;
use App\Models\Prestation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaiementController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'fille_id' => ['nullable', 'integer', 'exists:filles,id'],
        ]);

        $query = Prestation::with(['cachets' => fn ($q) => $q->where('statut', '!=', StatutCachet::Annule)])
            ->orderByDesc('date');

        if ($request->filled('date_debut')) {
            $query->whereDate('date', '>=', $request->date('date_debut'));
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('date', '<=', $request->date('date_fin'));
        }

        if ($request->filled('fille_id')) {
            $query->whereHas('cachets', fn ($q) => $q->where('fille_id', $request->integer('fille_id'))
                ->where('statut', '!=', StatutCachet::Annule));
        }

        $prestations = $query->get()->map(function (Prestation $prestation) {
            $du = $prestation->cachets->sum(fn ($c) => (float) $c->montant);
            $paye = $prestation->cachets->where('statut', StatutCachet::ValideePayee)->sum(fn ($c) => (float) $c->montant);

            return [
                'prestation' => $prestation,
                'total_du' => $du,
                'total_paye' => $paye,
                'reste_a_payer' => $du - $paye,
            ];
        });

        $filles = Fille::orderBy('nom')->get();

        return view('admin.paiements.index', compact('prestations', 'filles'));
    }
}
