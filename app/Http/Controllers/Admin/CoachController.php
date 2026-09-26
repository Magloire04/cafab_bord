<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutPersonne;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCoachRequest;
use App\Http\Requests\Admin\UpdateCoachRequest;
use App\Models\Coach;
use App\Models\User;
use App\Services\ChangementMotDePasse;
use App\Services\GenerateurMotDePasse;
use App\Services\PinGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class CoachController extends Controller
{
    public function index(Request $request): Response
    {
        $coaches = Coach::with('user')->orderBy('created_at', 'desc')->paginate(20);
        $response = response()->view('admin.coaches.index', compact('coaches'));

        // Le mot de passe provisoire n'est affiché qu'une fois : le bouton
        // Précédent ne doit pas le ressortir du cache du navigateur.
        if ($request->session()->has('identifiants')) {
            $response->header('Cache-Control', 'no-store');
        }

        return $response;
    }

    public function create(GenerateurMotDePasse $generateur): Response
    {
        return response()
            ->view('admin.coaches.create', ['motDePasse' => $generateur->generer()])
            ->header('Cache-Control', 'no-store');
    }

    public function store(StoreCoachRequest $request, PinGenerator $pinGenerator): RedirectResponse
    {
        DB::transaction(function () use ($request, $pinGenerator) {
            $user = User::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'role' => UserRole::Coach,
                'password' => Hash::make($request->validated('password')),
                'must_change_password' => true,
            ]);

            Coach::create([
                'user_id' => $user->id,
                'pin' => $pinGenerator->generate(),
                'contact' => $request->string('contact')->value() ?: null,
                'date_entree' => $request->date('date_entree'),
            ]);
        });

        return redirect()->route('admin.coaches.index')
            ->with('message', 'Coach ajouté.')
            ->with('identifiants', [
                'nom' => $request->validated('name'),
                'email' => $request->validated('email'),
                'mot_de_passe' => $request->validated('password'),
            ]);
    }

    public function edit(Coach $coach): View
    {
        return view('admin.coaches.edit', compact('coach'));
    }

    public function update(UpdateCoachRequest $request, Coach $coach): RedirectResponse
    {
        DB::transaction(function () use ($request, $coach) {
            $coach->user->update([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
            ]);

            $coach->update([
                'contact' => $request->string('contact')->value() ?: null,
                'date_entree' => $request->date('date_entree'),
            ]);
        });

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

    public function resetPassword(Coach $coach, GenerateurMotDePasse $generateur, ChangementMotDePasse $changement): RedirectResponse
    {
        $motDePasse = $generateur->generer();
        $changement->imposerProvisoire($coach->user, $motDePasse);

        return redirect()->route('admin.coaches.index')
            ->with('message', 'Mot de passe réinitialisé. Les sessions du coach ont été fermées.')
            ->with('identifiants', [
                'nom' => $coach->user->name,
                'email' => $coach->user->email,
                'mot_de_passe' => $motDePasse,
            ]);
    }
}
