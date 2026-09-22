<?php

use App\Http\Controllers\Admin\CoachController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:admin'])->get('/admin/ping', fn () => 'pong');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('coaches', CoachController::class)->except(['show', 'destroy']);
    Route::patch('coaches/{coach}/toggle-statut', [CoachController::class, 'toggleStatut'])
        ->name('coaches.toggle-statut');
    Route::patch('coaches/{coach}/regenerate-pin', [CoachController::class, 'regeneratePin'])
        ->name('coaches.regenerate-pin');
});

require __DIR__.'/auth.php';
