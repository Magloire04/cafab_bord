<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutPersonne;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFilleRequest;
use App\Http\Requests\Admin\UpdateFilleRequest;
use App\Models\Fille;
use App\Services\PinGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FilleController extends Controller
{
    public function index(): View
    {
        $filles = Fille::orderBy('nom')->paginate(20);

        return view('admin.filles.index', compact('filles'));
    }

    public function create(): View
    {
        return view('admin.filles.create');
    }

    public function store(StoreFilleRequest $request, PinGenerator $pinGenerator): RedirectResponse
    {
        Fille::create([
            'nom' => $request->string('nom'),
            'prenom' => $request->string('prenom'),
            'contact' => $request->string('contact')->value() ?: null,
            'pin' => $pinGenerator->generate(),
            'date_entree' => $request->date('date_entree'),
        ]);

        return redirect()->route('admin.filles.index')->with('message', 'Fille ajoutée.');
    }

    public function edit(Fille $fille): View
    {
        return view('admin.filles.edit', compact('fille'));
    }

    public function update(UpdateFilleRequest $request, Fille $fille): RedirectResponse
    {
        $fille->update([
            'nom' => $request->string('nom'),
            'prenom' => $request->string('prenom'),
            'contact' => $request->string('contact')->value() ?: null,
            'date_entree' => $request->date('date_entree'),
        ]);

        return redirect()->route('admin.filles.index')->with('message', 'Fiche mise à jour.');
    }

    public function toggleStatut(Fille $fille): RedirectResponse
    {
        $fille->update([
            'statut' => $fille->statut === StatutPersonne::Actif
                ? StatutPersonne::Inactif
                : StatutPersonne::Actif,
        ]);

        return redirect()->route('admin.filles.index')->with('message', 'Statut mis à jour.');
    }

    public function regeneratePin(Fille $fille, PinGenerator $pinGenerator): RedirectResponse
    {
        $fille->update(['pin' => $pinGenerator->generate()]);

        return redirect()->route('admin.filles.index')->with('message', 'Nouveau code PIN généré.');
    }
}
