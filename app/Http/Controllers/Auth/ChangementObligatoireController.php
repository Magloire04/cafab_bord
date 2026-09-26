<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ChangementMotDePasse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ChangementObligatoireController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.changer-mot-de-passe');
    }

    public function update(Request $request, ChangementMotDePasse $changement): RedirectResponse
    {
        // Sans changement en attente, on passe par le profil, qui exige le mot de passe actuel.
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (Hash::check($validated['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Choisissez un mot de passe différent du mot de passe provisoire.']);
        }

        $changement->definirPourSessionCourante($request, $validated['password']);

        return redirect()->route('dashboard')->with('message', 'Mot de passe enregistré. Bienvenue !');
    }
}
