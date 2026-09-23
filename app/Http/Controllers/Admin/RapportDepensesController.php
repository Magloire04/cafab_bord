<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DepensesPrestationsExport;
use App\Http\Controllers\Controller;
use App\Models\Cachet;
use App\Models\Fille;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RapportDepensesController extends Controller
{
    public function index(Request $request): View
    {
        $filtres = $this->filtresValides($request);

        $cachets = Cachet::validees()
            ->entrePeriode($filtres['date_debut'], $filtres['date_fin'])
            ->pourFille($filtres['fille_id'])
            ->with(['prestation', 'fille'])
            ->orderByDesc('validee_at')
            ->get();

        return view('admin.rapports.depenses', [
            'cachets' => $cachets,
            'total' => $cachets->sum(fn (Cachet $c) => (float) $c->montant),
            'filles' => Fille::orderBy('nom')->get(),
        ]);
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $filtres = $this->filtresValides($request);

        $nomFichier = 'Rapport_Depenses_Prestations_'.now()->format('Y-m-d_Hi').'.xlsx';

        return Excel::download(new DepensesPrestationsExport($filtres), $nomFichier);
    }

    /**
     * @return array{date_debut: ?string, date_fin: ?string, fille_id: ?int}
     */
    private function filtresValides(Request $request): array
    {
        $valides = $request->validate([
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'fille_id' => ['nullable', 'integer', 'exists:filles,id'],
        ]);

        return [
            'date_debut' => $valides['date_debut'] ?? null,
            'date_fin' => $valides['date_fin'] ?? null,
            'fille_id' => isset($valides['fille_id']) ? (int) $valides['fille_id'] : null,
        ];
    }
}
