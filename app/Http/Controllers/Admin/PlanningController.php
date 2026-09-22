<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanningRequest;
use App\Http\Requests\Admin\UpdatePlanningRequest;
use App\Models\Coach;
use App\Models\PlanningRepetition;
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

        return redirect()->route('admin.plannings.index')->with('message', 'Créneau mis à jour.');
    }

    public function toggleActif(PlanningRepetition $planning): RedirectResponse
    {
        $planning->update(['actif' => ! $planning->actif]);

        return redirect()->route('admin.plannings.index')->with('message', 'Statut du créneau mis à jour.');
    }
}
