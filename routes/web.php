<?php

use App\Http\Controllers\Admin\CachetController as AdminCachetController;
use App\Http\Controllers\Admin\CalendrierController;
use App\Http\Controllers\Admin\CoachController;
use App\Http\Controllers\Admin\PaiementController;
use App\Http\Controllers\Admin\PointageController as AdminPointageController;
use App\Http\Controllers\Admin\PrestationController;
use App\Http\Controllers\Admin\RapportDepensesController;
use App\Http\Controllers\Admin\RapportPonctualiteController;
use App\Http\Controllers\Coach\PointageController as CoachPointageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EtatSeancesController;
use App\Http\Controllers\Kiosque\CachetController as KiosqueCachetController;
use App\Http\Controllers\Kiosque\EtatController as KiosqueEtatController;
use App\Http\Controllers\Kiosque\IdentificationController;
use App\Http\Controllers\Kiosque\PointageController as KiosquePointageController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Registre\FilleController;
use App\Http\Controllers\Registre\FilleImportController;
use App\Http\Controllers\SeanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'role:admin'])->get('/admin/ping', fn () => 'pong');

Route::middleware(['auth', 'role:admin,coach'])->group(function () {
    Route::get('seances/extraordinaire/creer', [SeanceController::class, 'create'])->name('seances.create-extraordinaire');
    Route::post('seances/extraordinaire', [SeanceController::class, 'store'])->name('seances.store-extraordinaire');

    Route::get('etat-seances', EtatSeancesController::class)->name('etat-seances');

    Route::prefix('registre')->group(function () {
        Route::get('filles/import', [FilleImportController::class, 'form'])->name('filles.import');
        Route::post('filles/import/preview', [FilleImportController::class, 'preview'])->name('filles.import.preview');
        Route::post('filles/import/confirm', [FilleImportController::class, 'confirm'])->name('filles.import.confirm');

        Route::resource('filles', FilleController::class)->except(['show', 'destroy']);
        Route::patch('filles/{fille}/toggle-statut', [FilleController::class, 'toggleStatut'])->name('filles.toggle-statut');
        Route::patch('filles/{fille}/regenerate-pin', [FilleController::class, 'regeneratePin'])->name('filles.regenerate-pin');
    });

    Route::resource('planning', PlanningController::class)->except(['show', 'destroy'])->names('plannings');
    Route::patch('planning/{planning}/toggle-actif', [PlanningController::class, 'toggleActif'])->name('plannings.toggle-actif');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('coaches', CoachController::class)->except(['show', 'destroy']);
    Route::patch('coaches/{coach}/toggle-statut', [CoachController::class, 'toggleStatut'])
        ->name('coaches.toggle-statut');
    Route::patch('coaches/{coach}/regenerate-pin', [CoachController::class, 'regeneratePin'])
        ->name('coaches.regenerate-pin');
    Route::patch('coaches/{coach}/reset-password', [CoachController::class, 'resetPassword'])
        ->name('coaches.reset-password');

    Route::get('calendrier', [CalendrierController::class, 'index'])->name('calendrier');

    Route::get('pointages', [AdminPointageController::class, 'index'])->name('pointages.index');
    Route::patch('pointages/{pointage}/corriger', [AdminPointageController::class, 'corriger'])->name('pointages.corriger');

    Route::get('rapports', function () {
        return view('admin.rapports.index');
    })->name('rapports.index');

    Route::get('rapports/ponctualite', [RapportPonctualiteController::class, 'index'])->name('rapports.ponctualite');
    Route::get('rapports/ponctualite/excel', [RapportPonctualiteController::class, 'excel'])->name('rapports.ponctualite.excel');

    Route::get('rapports/depenses', [RapportDepensesController::class, 'index'])->name('rapports.depenses');
    Route::get('rapports/depenses/excel', [RapportDepensesController::class, 'excel'])->name('rapports.depenses.excel');

    Route::resource('prestations', PrestationController::class)->only(['index', 'create', 'store', 'show']);
    Route::patch('prestations/{prestation}/annuler', [PrestationController::class, 'annuler'])
        ->name('prestations.annuler');

    Route::get('paiements', [PaiementController::class, 'index'])->name('paiements.index');

    Route::patch('cachets/{cachet}/valider', [AdminCachetController::class, 'valider'])->name('cachets.valider');
    Route::patch('cachets/{cachet}/corriger', [AdminCachetController::class, 'corriger'])->name('cachets.corriger');
    Route::patch('cachets/{cachet}/montant', [AdminCachetController::class, 'ajusterMontant'])->name('cachets.ajuster-montant');
    Route::patch('cachets/{cachet}/reessayer-depense', [AdminCachetController::class, 'reessayerDepense'])
        ->name('cachets.reessayer-depense');
});

Route::middleware(['auth', 'role:coach'])->prefix('coach')->name('coach.')->group(function () {
    Route::get('ma-seance', [CoachPointageController::class, 'show'])->name('seance');
    Route::patch('ma-seance/marquer-presente', [CoachPointageController::class, 'marquerPresente'])->name('seance.marquer-presente');
    Route::patch('ma-seance/cloturer', [CoachPointageController::class, 'cloturer'])->name('seance.cloturer');
    Route::get('mon-historique', [CoachPointageController::class, 'historique'])->name('historique');
});

Route::prefix('kiosque')->name('kiosque.')->group(function () {
    Route::get('/', [IdentificationController::class, 'home'])->name('home');
    Route::post('identifier', [IdentificationController::class, 'identifier'])
        ->middleware('throttle:20,1')
        ->name('identifier');
    Route::get('menu', [IdentificationController::class, 'menu'])->name('menu');
    Route::post('pointer', [KiosquePointageController::class, 'store'])->name('pointer');

    Route::get('cachets', [KiosqueCachetController::class, 'index'])->name('cachets.index');
    Route::post('cachets/{cachet}/declarer', [KiosqueCachetController::class, 'declarer'])->name('cachets.declarer');

    Route::get('etat', KiosqueEtatController::class)->name('etat');
});

require __DIR__.'/auth.php';
