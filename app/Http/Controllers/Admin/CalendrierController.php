<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutPonctualite;
use App\Enums\StatutSeance;
use App\Http\Controllers\Controller;
use App\Models\Seance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CalendrierController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['mois' => ['nullable', 'date_format:Y-m']]);

        $mois = $request->filled('mois')
            ? Carbon::createFromFormat('!Y-m', $request->string('mois'))
            : Carbon::now();

        $debut = $mois->copy()->startOfMonth();
        $fin = $mois->copy()->endOfMonth();

        $seances = Seance::whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->with('pointages')
            ->orderBy('date')
            ->get()
            ->map(function (Seance $seance) {
                $seance->taux_presence = $this->tauxPresence($seance);

                return $seance;
            });

        return view('admin.calendrier.index', [
            'mois' => $mois,
            'seances' => $seances,
        ]);
    }

    private function tauxPresence(Seance $seance): ?int
    {
        if ($seance->statut !== StatutSeance::Cloturee || $seance->pointages->isEmpty()) {
            return null;
        }

        $presentes = $seance->pointages->filter(
            fn ($p) => $p->statut_ponctualite !== StatutPonctualite::Absent
        )->count();

        return (int) round(($presentes / $seance->pointages->count()) * 100);
    }
}
