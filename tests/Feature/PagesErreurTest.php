<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('shows a French 404 page with the CAFAB logo and a way back', function () {
    $this->get('/cette-page-n-existe-pas')
        ->assertNotFound()
        ->assertSee('Page introuvable')
        ->assertSee('Cette adresse ne correspond à aucune page.')
        ->assertSee('images/logo-cafab.png', false)
        ->assertSee("Retour à l'accueil");
});

it('shows a French 403 page', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get('/admin/ping')
        ->assertForbidden()
        ->assertSee('Accès refusé')
        ->assertSee("Vous n'avez pas les droits pour ouvrir cette page.");
});

it('shows a French 419 page outside the kiosk', function () {
    Route::middleware('web')->post('/essai-page-expiree', fn () => throw new TokenMismatchException);

    $this->post('/essai-page-expiree')
        ->assertStatus(419)
        ->assertSee('Page expirée')
        ->assertSee('La page est restée ouverte trop longtemps. Rechargez-la puis recommencez.');
});

it('shows a French 429 page outside the kiosk', function () {
    Route::middleware('web')->get('/essai-trop-de-tentatives', fn () => throw new ThrottleRequestsException);

    $this->get('/essai-trop-de-tentatives')
        ->assertStatus(429)
        ->assertSee('Trop de tentatives')
        ->assertSee('Patientez un peu avant de réessayer.');
});

it('shows a French 500 page without any technical detail', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/essai-erreur', fn () => throw new RuntimeException('Détail interne à ne pas montrer'));

    $this->get('/essai-erreur')
        ->assertStatus(500)
        ->assertSee('Erreur inattendue')
        ->assertSee('Un problème est survenu de notre côté. Réessayez dans un instant.')
        ->assertDontSee('Détail interne à ne pas montrer')
        ->assertDontSee('RuntimeException');
});

it('shows a French 503 page without the way back', function () {
    Route::get('/essai-maintenance', fn () => abort(503));

    $this->get('/essai-maintenance')
        ->assertStatus(503)
        ->assertSee('Maintenance en cours')
        ->assertSee("L'application revient dans quelques minutes.")
        ->assertDontSee("Retour à l'accueil");
});

it('renders every error page without querying the database', function (int $code) {
    DB::enableQueryLog();

    view("errors.{$code}")->render();

    expect(DB::getQueryLog())->toBeEmpty();
})->with([403, 404, 419, 429, 500, 503]);

it('sends the kiosk back to the code screen when its page has expired', function () {
    Route::middleware('web')->post('/kiosque/essai-page-expiree', fn () => throw new TokenMismatchException);

    $this->post('/kiosque/essai-page-expiree')
        ->assertRedirect(route('kiosque.home'))
        ->assertSessionHasErrors(['pin' => 'La page avait expiré. Tape à nouveau ton code.']);
});

it('keeps the French 404 page for an unknown kiosk address', function () {
    $this->get('/kiosque/adresse-inconnue')
        ->assertNotFound()
        ->assertSee('Page introuvable');
});
