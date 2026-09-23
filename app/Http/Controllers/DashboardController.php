<?php

namespace App\Http\Controllers;

use App\Enums\StatutCachet;
use App\Enums\StatutPersonne;
use App\Enums\StatutSeance;
use App\Enums\UserRole;
use App\Models\Cachet;
use App\Models\Coach;
use App\Models\Fille;
use App\Models\Seance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->role === UserRole::Admin) {
            return view('dashboard', [
                'stats' => [
                    'coachsActifs' => Coach::where('statut', StatutPersonne::Actif)->count(),
                    'fillesActives' => Fille::where('statut', StatutPersonne::Actif)->count(),
                    'seancesAujourdhui' => Seance::whereDate('date', today())->count(),
                    'seancesEnCours' => Seance::where('statut', StatutSeance::EnCours)->count(),
                    'cachetsDusMontant' => (float) Cachet::where('statut', StatutCachet::Du)->sum('montant'),
                    'cachetsAValider' => Cachet::where('statut', StatutCachet::DeclareePayee)->count(),
                ],
            ]);
        }

        $seanceDuJour = $user->coach
            ? Seance::where('coach_id', $user->coach->id)->whereDate('date', today())->first()
            : null;

        return view('dashboard', [
            'seanceDuJour' => $seanceDuJour,
        ]);
    }
}
