<?php

use App\Enums\StatutPersonne;
use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\User;
use App\Services\GenerateurMotDePasse;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('blocks a coach from the registre', function () {
    $coach = User::factory()->create(['role' => UserRole::Coach]);

    $this->actingAs($coach)->get(route('admin.coaches.index'))->assertForbidden();
});

it('lets the admin list coaches', function () {
    Coach::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.coaches.index'))
        ->assertOk();
});

it('lets the admin create a coach with a provisional password to change at first login', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.coaches.store'), [
        'name' => 'Prudence Aïvodji',
        'email' => '  Prudence@CAFAB.bj ',
        'password' => 'Provisoire-7!',
        'contact' => '+229 01 00 00 00 00',
        'date_entree' => '2026-01-15',
    ]);

    $response->assertRedirect(route('admin.coaches.index'));
    $response->assertSessionHas('identifiants', [
        'nom' => 'Prudence Aïvodji',
        'email' => 'prudence@cafab.bj',
        'mot_de_passe' => 'Provisoire-7!',
    ]);

    $user = User::where('email', 'prudence@cafab.bj')->firstOrFail();
    expect($user->role)->toBe(UserRole::Coach);
    expect($user->must_change_password)->toBeTrue();
    expect(Hash::check('Provisoire-7!', $user->password))->toBeTrue();
    expect($user->coach->pin)->toMatch('/^\d{4}$/');
    expect($user->coach->statut)->toBe(StatutPersonne::Actif);
});

it('pre-fills the creation form with a compliant provisional password', function () {
    $this->actingAs($this->admin)->get(route('admin.coaches.create'))
        ->assertOk()
        ->assertViewHas('motDePasse', fn (string $motDePasse) => validator(['p' => $motDePasse], ['p' => GenerateurMotDePasse::regle()])->passes());
});

it('refuses a weak provisional password', function () {
    $this->actingAs($this->admin)->post(route('admin.coaches.store'), [
        'name' => 'Test',
        'email' => 'test@cafab.bj',
        'password' => 'motdepasse',
        'date_entree' => '2026-01-15',
    ])->assertSessionHasErrors('password');
});

it('shows the credentials to hand over once, on the list', function () {
    $this->actingAs($this->admin)
        ->withSession(['identifiants' => ['nom' => 'Prudence', 'email' => 'prudence@cafab.bj', 'mot_de_passe' => 'Provisoire-7!']])
        ->get(route('admin.coaches.index'))
        ->assertSee('Identifiants à transmettre')
        ->assertSee('Provisoire-7!');
});

it('keeps the pre-filled creation form out of the browser cache and password manager', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.coaches.create'))->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $response->assertSee('id="email" name="email" type="email" autocomplete="off"', false);
});

it('keeps the list out of the browser cache while it shows credentials', function () {
    $response = $this->actingAs($this->admin)
        ->withSession(['identifiants' => ['nom' => 'Prudence', 'email' => 'prudence@cafab.bj', 'mot_de_passe' => 'Provisoire-7!']])
        ->get(route('admin.coaches.index'));

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('shows the credentials of a new coach on the first list only, uncached', function () {
    $this->actingAs($this->admin)->post(route('admin.coaches.store'), [
        'name' => 'Prudence Aïvodji',
        'email' => 'prudence@cafab.bj',
        'password' => 'Provisoire-7!',
        'date_entree' => '2026-01-15',
    ])->assertRedirect(route('admin.coaches.index'));

    $premiere = $this->get(route('admin.coaches.index'))->assertSee('Provisoire-7!');
    expect($premiere->headers->get('Cache-Control'))->toContain('no-store');

    $seconde = $this->get(route('admin.coaches.index'))
        ->assertDontSee('Identifiants à transmettre')
        ->assertDontSee('Provisoire-7!');
    expect($seconde->headers->get('Cache-Control'))->not->toContain('no-store');
});

it('lets the admin edit every field of a coach', function () {
    $coach = Coach::factory()->create(['contact' => '+229 00 00 00 00 00']);

    $this->actingAs($this->admin)->put(route('admin.coaches.update', $coach), [
        'name' => 'Maurice Gnonlonfoun',
        'email' => ' Maurice@CAFAB.bj',
        'contact' => '+229 11 11 11 11 11',
        'date_entree' => '2025-10-01',
    ])->assertRedirect(route('admin.coaches.index'));

    $coach->refresh();
    expect($coach->user->name)->toBe('Maurice Gnonlonfoun');
    expect($coach->user->email)->toBe('maurice@cafab.bj');
    expect($coach->contact)->toBe('+229 11 11 11 11 11');
    expect($coach->date_entree->format('Y-m-d'))->toBe('2025-10-01');
});

it('refuses an email already used by another account, but accepts keeping one\'s own', function () {
    User::factory()->create(['email' => 'pris@cafab.bj']);
    $coach = Coach::factory()->create();
    $donnees = ['name' => $coach->user->name, 'date_entree' => '2026-01-15'];

    $this->actingAs($this->admin)->put(route('admin.coaches.update', $coach), [...$donnees, 'email' => 'Pris@cafab.bj'])
        ->assertSessionHasErrors('email');
    $this->actingAs($this->admin)->put(route('admin.coaches.update', $coach), [...$donnees, 'email' => $coach->user->email])
        ->assertSessionHasNoErrors();
});

it('lets the admin reset a coach password', function () {
    $coach = Coach::factory()->create();

    $response = $this->actingAs($this->admin)->patch(route('admin.coaches.reset-password', $coach));

    $response->assertRedirect(route('admin.coaches.index'));
    $motDePasse = session('identifiants')['mot_de_passe'];
    expect(Hash::check($motDePasse, $coach->user->fresh()->password))->toBeTrue();
    expect($coach->user->fresh()->must_change_password)->toBeTrue();
});

it('groups the row actions in a menu with confirmations', function () {
    Coach::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.coaches.index'))
        ->assertSee('Réinitialiser le mot de passe')
        ->assertSee('data-confirmer', false)
        ->assertSee('modale-confirmation', false);
});

it('forbids the reset to a coach', function () {
    $coach = Coach::factory()->create();

    $this->actingAs($coach->user)->patch(route('admin.coaches.reset-password', $coach))->assertForbidden();
});

it('lets the admin deactivate then reactivate a coach', function () {
    $coach = Coach::factory()->create(['statut' => 'actif']);

    $this->actingAs($this->admin)->patch(route('admin.coaches.toggle-statut', $coach));
    expect($coach->fresh()->statut)->toBe(StatutPersonne::Inactif);

    $this->actingAs($this->admin)->patch(route('admin.coaches.toggle-statut', $coach));
    expect($coach->fresh()->statut)->toBe(StatutPersonne::Actif);
});

it('lets the admin regenerate a coach pin', function () {
    $coach = Coach::factory()->create(['pin' => '9999']);

    $this->actingAs($this->admin)->patch(route('admin.coaches.regenerate-pin', $coach));

    expect($coach->fresh()->pin)->not->toBe('9999');
});
