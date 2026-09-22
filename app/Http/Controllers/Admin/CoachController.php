<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutPersonne;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCoachRequest;
use App\Http\Requests\Admin\UpdateCoachRequest;
use App\Models\Coach;
use App\Models\User;
use App\Services\PinGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CoachController extends Controller
{
    public function index(): View
    {
        $coaches = Coach::with('user')->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.coaches.index', compact('coaches'));
    }

    public function create(): View
    {
        return view('admin.coaches.create');
    }

    public function store(StoreCoachRequest $request, PinGenerator $pinGenerator): RedirectResponse
    {
        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'role' => UserRole::Coach,
            'password' => Hash::make(Str::password(16)),
        ]);

        Coach::create([
            'user_id' => $user->id,
            'pin' => $pinGenerator->generate(),
            'contact' => $request->string('contact')->value() ?: null,
            'date_entree' => $request->date('date_entree'),
        ]);

        return redirect()->route('admin.coaches.index')->with('message', 'Coach ajouté.');
    }

    public function edit(Coach $coach): View
    {
        return view('admin.coaches.edit', compact('coach'));
    }

    public function update(UpdateCoachRequest $request, Coach $coach): RedirectResponse
    {
        $coach->update([
            'contact' => $request->string('contact')->value() ?: null,
            'date_entree' => $request->date('date_entree'),
        ]);

        return redirect()->route('admin.coaches.index')->with('message', 'Coach mis à jour.');
    }

    public function toggleStatut(Coach $coach): RedirectResponse
    {
        $coach->update([
            'statut' => $coach->statut === StatutPersonne::Actif
                ? StatutPersonne::Inactif
                : StatutPersonne::Actif,
        ]);

        return redirect()->route('admin.coaches.index')->with('message', 'Statut mis à jour.');
    }

    public function regeneratePin(Coach $coach, PinGenerator $pinGenerator): RedirectResponse
    {
        $coach->update(['pin' => $pinGenerator->generate()]);

        return redirect()->route('admin.coaches.index')->with('message', 'Nouveau code PIN généré.');
    }
}
