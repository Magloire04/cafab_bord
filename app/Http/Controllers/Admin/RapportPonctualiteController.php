<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PonctualiteExport;
use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\Fille;
use App\Services\PonctualiteRapportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RapportPonctualiteController extends Controller
{
    public function index(Request $request, PonctualiteRapportService $service): View
    {
        $filtres = $this->filtresValides($request);

        $lignes = $service->generer($filtres['date_debut'], $filtres['date_fin'], $filtres['fille_id'], $filtres['coach_id']);

        return view('admin.rapports.ponctualite', [
            'lignes' => $lignes,
            'filles' => Fille::orderBy('nom')->get(),
            'coaches' => Coach::with('user')->get(),
        ]);
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $filtres = $this->filtresValides($request);

        $nomFichier = 'Rapport_Ponctualite_'.now()->format('Y-m-d_Hi').'.xlsx';

        return Excel::download(new PonctualiteExport($filtres), $nomFichier);
    }

    /**
     * @return array{date_debut: ?string, date_fin: ?string, fille_id: ?int, coach_id: ?int}
     */
    private function filtresValides(Request $request): array
    {
        $valides = $request->validate([
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'fille_id' => ['nullable', 'integer', 'exists:filles,id'],
            'coach_id' => ['nullable', 'integer', 'exists:coaches,id'],
        ]);

        return [
            'date_debut' => $valides['date_debut'] ?? null,
            'date_fin' => $valides['date_fin'] ?? null,
            'fille_id' => isset($valides['fille_id']) ? (int) $valides['fille_id'] : null,
            'coach_id' => isset($valides['coach_id']) ? (int) $valides['coach_id'] : null,
        ];
    }
}
