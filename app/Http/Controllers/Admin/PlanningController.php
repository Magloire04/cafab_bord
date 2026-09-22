<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanningRequest;
use App\Http\Requests\Admin\UpdatePlanningRequest;
use App\Models\Coach;
use App\Models\PlanningRepetition;
use App\Models\Seance;
use App\Services\SeanceGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function index(): View
    {
        $plannings = PlanningRepetition::with('coach.user')->orderBy('jour_semaine')->orderBy('heure_debut')->get();

        return view('admin.plannings.index', compact('plannings'));
    }

    public function create(): View
    {
        $coaches = Coach::with('user')->where('statut', 'actif')->get();

        return view('admin.plannings.create', compact('coaches'));
    }

    public function store(StorePlanningRequest $request): RedirectResponse
    {
        PlanningRepetition::create($request->validated());

        return redirect()->route('admin.plannings.index')->with('message', 'Créneau ajouté.');
    }

    public function edit(PlanningRepetition $planning): View
    {
        $coaches = Coach::with('user')->where('statut', 'actif')->get();

        return view('admin.plannings.edit', compact('planning', 'coaches'));
    }

    public function update(UpdatePlanningRequest $request, PlanningRepetition $planning): RedirectResponse
    {
        $planning->update($request->validated());

        $this->reconcilerSeancesFutures($planning);

        return redirect()->route('admin.plannings.index')->with('message', 'Créneau mis à jour.');
    }

    public function toggleActif(PlanningRepetition $planning): RedirectResponse
    {
        $planning->update(['actif' => ! $planning->actif]);

        $this->reconcilerSeancesFutures($planning);

        return redirect()->route('admin.plannings.index')->with('message', 'Statut du créneau mis à jour.');
    }

    /**
     * SeanceGenerator skips any date that already has a séance for a given
     * planning, so an edit to a slot's time (or deactivating it) would
     * otherwise leave up to 14 days of already-generated future a_venir
     * séances stuck with the old time, or existing at all for a now-
     * deactivated slot. Deleting them is safe: they're future and a_venir,
     * so they can never have any Pointage rows attached. If the planning
     * is still active, regenerate immediately so the corrected séances
     * reappear right away instead of waiting for tomorrow's scheduled run.
     */
    private function reconcilerSeancesFutures(PlanningRepetition $planning): void
    {
        Seance::where('planning_repetition_id', $planning->id)
            ->where('date', '>', today())
            ->where('statut', 'a_venir')
            ->delete();

        if ($planning->actif) {
            app(SeanceGenerator::class)->genererPourLesProchainsJours();
        }
    }
}
