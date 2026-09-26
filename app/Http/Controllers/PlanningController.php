<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Planning\StorePlanningRequest;
use App\Http\Requests\Planning\UpdatePlanningRequest;
use App\Models\Coach;
use App\Models\PlanningRepetition;
use App\Models\Seance;
use App\Services\SeanceCycleDeVie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function __construct(private SeanceCycleDeVie $cycleDeVie) {}

    public function index(): View
    {
        $plannings = PlanningRepetition::with('coach.user')->orderBy('jour_semaine')->orderBy('heure_debut')->get();

        return view('plannings.index', compact('plannings'));
    }

    public function create(): View
    {
        Gate::authorize('create', PlanningRepetition::class);

        $coaches = Coach::with('user')->where('statut', 'actif')->get();

        return view('plannings.create', compact('coaches'));
    }

    // Autorisation : StorePlanningRequest::authorize().
    public function store(StorePlanningRequest $request): RedirectResponse
    {
        PlanningRepetition::create([
            'jour_semaine' => $request->validated('jour_semaine'),
            'heure_debut' => $request->validated('heure_debut'),
            'coach_id' => $this->coachReferent($request),
        ]);

        $this->cycleDeVie->synchroniser();

        return redirect()->route('plannings.index')->with('message', 'Créneau ajouté.');
    }

    public function edit(PlanningRepetition $planning): View
    {
        Gate::authorize('update', $planning);

        $coaches = Coach::with('user')->where('statut', 'actif')->get();

        return view('plannings.edit', compact('planning', 'coaches'));
    }

    // Autorisation : UpdatePlanningRequest::authorize().
    public function update(UpdatePlanningRequest $request, PlanningRepetition $planning): RedirectResponse
    {
        $planning->update([
            'jour_semaine' => $request->validated('jour_semaine'),
            'heure_debut' => $request->validated('heure_debut'),
            'coach_id' => $this->coachReferent($request),
        ]);

        $this->reconcilerSeancesFutures($planning);

        return redirect()->route('plannings.index')->with('message', 'Créneau mis à jour.');
    }

    public function toggleActif(PlanningRepetition $planning): RedirectResponse
    {
        Gate::authorize('update', $planning);

        $planning->update(['actif' => ! $planning->actif]);

        $this->reconcilerSeancesFutures($planning);

        return redirect()->route('plannings.index')->with('message', 'Statut du créneau mis à jour.');
    }

    /**
     * Un coach ne crée et ne modifie que ses propres créneaux, quel que soit le coach_id envoyé.
     */
    private function coachReferent(Request $request): int
    {
        return $request->user()->role === UserRole::Admin
            ? (int) $request->input('coach_id')
            : $request->user()->coach->id;
    }

    /**
     * SeanceGenerator ignore toute date qui a déjà une séance pour ce créneau :
     * après un changement d'heure ou une désactivation, les séances « à venir »
     * d'aujourd'hui et des jours suivants sont supprimées puis régénérées tout
     * de suite. Elles ne portent aucun pointage (PointageService::pointer exige
     * une séance en cours). Celle du jour n'est recréée que si sa nouvelle heure
     * est encore à venir ; une séance déjà démarrée n'est jamais touchée.
     */
    private function reconcilerSeancesFutures(PlanningRepetition $planning): void
    {
        Seance::where('planning_repetition_id', $planning->id)
            ->whereDate('date', '>=', today())
            ->where('statut', 'a_venir')
            ->delete();

        $this->cycleDeVie->synchroniser();
    }
}
