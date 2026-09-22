<?php

use App\Http\Controllers\Admin\CoachController;
use App\Http\Controllers\Admin\FilleController;
use App\Http\Controllers\Admin\FilleImportController;
use App\Http\Controllers\Admin\PlanningController;
use App\Http\Controllers\Coach\PointageController as CoachPointageController;
use App\Http\Controllers\Kiosque\IdentificationController;
use App\Http\Controllers\Kiosque\PointageController as KiosquePointageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SeanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'role:admin'])->get('/admin/ping', fn () => 'pong');

Route::middleware(['auth', 'role:admin,coach'])->group(function () {
    Route::get('seances/extraordinaire/creer', [SeanceController::class, 'create'])->name('seances.create-extraordinaire');
    Route::post('seances/extraordinaire', [SeanceController::class, 'store'])->name('seances.store-extraordinaire');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('coaches', CoachController::class)->except(['show', 'destroy']);
    Route::patch('coaches/{coach}/toggle-statut', [CoachController::class, 'toggleStatut'])
        ->name('coaches.toggle-statut');
    Route::patch('coaches/{coach}/regenerate-pin', [CoachController::class, 'regeneratePin'])
        ->name('coaches.regenerate-pin');

    Route::resource('filles', FilleController::class)->except(['show', 'destroy']);
    Route::patch('filles/{fille}/toggle-statut', [FilleController::class, 'toggleStatut'])
        ->name('filles.toggle-statut');
    Route::patch('filles/{fille}/regenerate-pin', [FilleController::class, 'regeneratePin'])
        ->name('filles.regenerate-pin');

    Route::get('filles/import', [FilleImportController::class, 'form'])->name('filles.import');
    Route::post('filles/import/preview', [FilleImportController::class, 'preview'])->name('filles.import.preview');
    Route::post('filles/import/confirm', [FilleImportController::class, 'confirm'])->name('filles.import.confirm');

    Route::resource('plannings', PlanningController::class)
        ->except(['show', 'destroy'])
        ->parameters(['plannings' => 'planning']);
    Route::patch('plannings/{planning}/toggle-actif', [PlanningController::class, 'toggleActif'])
        ->name('plannings.toggle-actif');
});

Route::middleware(['auth', 'role:coach'])->prefix('coach')->name('coach.')->group(function () {
    Route::get('ma-seance', [CoachPointageController::class, 'show'])->name('seance');
    Route::patch('ma-seance/marquer-presente', [CoachPointageController::class, 'marquerPresente'])->name('seance.marquer-presente');
    Route::patch('ma-seance/cloturer', [CoachPointageController::class, 'cloturer'])->name('seance.cloturer');
});

Route::prefix('kiosque')->name('kiosque.')->group(function () {
    Route::get('/', [IdentificationController::class, 'home'])->name('home');
    Route::post('identifier', [IdentificationController::class, 'identifier'])
        ->middleware('throttle:20,1')
        ->name('identifier');
    Route::get('menu', [IdentificationController::class, 'menu'])->name('menu');
    Route::post('pointer', [KiosquePointageController::class, 'store'])->name('pointer');
});

require __DIR__.'/auth.php';
