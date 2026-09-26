<?php

use App\Models\Fille;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

const MESSAGE_BLOCAGE_15_MIN = 'Trop de codes erronés. Réessaie dans 15 minutes, ou demande à ton coach de te pointer.';

function envoyerCodesFauxAuKiosque(TestCase $test, int $nombre, string $ip = '127.0.0.1'): void
{
    foreach (range(1, $nombre) as $essai) {
        $test->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('kiosque.identifier'), ['pin' => '0000']);
    }
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-27 17:00:00');
    Fille::factory()->create(['pin' => '1234']);
});

afterEach(fn () => Carbon::setTestNow());

it('still accepts a good code after nine wrong ones', function () {
    envoyerCodesFauxAuKiosque($this, 9);

    $this->post(route('kiosque.identifier'), ['pin' => '1234'])
        ->assertRedirect(route('kiosque.menu'));
});

it('announces the block on the tenth wrong code', function () {
    envoyerCodesFauxAuKiosque($this, 9);

    $this->post(route('kiosque.identifier'), ['pin' => '0000'])
        ->assertRedirect(route('kiosque.home'))
        ->assertSessionHasErrors(['pin' => MESSAGE_BLOCAGE_15_MIN]);
});

it('refuses even a good code while the address is blocked', function () {
    envoyerCodesFauxAuKiosque($this, 10);

    $this->post(route('kiosque.identifier'), ['pin' => '1234'])
        ->assertRedirect(route('kiosque.home'))
        ->assertSessionHasErrors(['pin' => MESSAGE_BLOCAGE_15_MIN]);

    expect(session('kiosque.identifie'))->toBeNull();
});

it('rounds the remaining time up to the minute, in the singular for one minute', function () {
    envoyerCodesFauxAuKiosque($this, 10);
    Carbon::setTestNow(now()->addMinutes(14)->addSeconds(30));

    $this->post(route('kiosque.identifier'), ['pin' => '1234'])
        ->assertSessionHasErrors(['pin' => 'Trop de codes erronés. Réessaie dans 1 minute, ou demande à ton coach de te pointer.']);
});

it('lets a good code through again 15 minutes after the block started', function () {
    envoyerCodesFauxAuKiosque($this, 10);
    Carbon::setTestNow(now()->addMinutes(15)->addSecond());

    $this->post(route('kiosque.identifier'), ['pin' => '1234'])
        ->assertRedirect(route('kiosque.menu'));
});

it('keeps counting wrong codes across good ones', function () {
    envoyerCodesFauxAuKiosque($this, 5);
    $this->post(route('kiosque.identifier'), ['pin' => '1234'])->assertRedirect(route('kiosque.menu'));
    envoyerCodesFauxAuKiosque($this, 5);

    $this->post(route('kiosque.identifier'), ['pin' => '1234'])
        ->assertSessionHasErrors(['pin' => MESSAGE_BLOCAGE_15_MIN]);
});

it('does not block another address', function () {
    envoyerCodesFauxAuKiosque($this, 10, '10.0.0.1');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->post(route('kiosque.identifier'), ['pin' => '1234'])
        ->assertRedirect(route('kiosque.menu'));
});

it('counts malformed or missing codes as wrong codes, without ever failing', function () {
    $envois = [['pin' => '12'], ['pin' => '12345'], [], ['pin' => ['1', '2', '3', '4']], ['pin' => '']];

    foreach (range(1, 2) as $tour) {
        foreach ($envois as $donnees) {
            $this->post(route('kiosque.identifier'), $donnees)->assertRedirect(route('kiosque.home'));
        }
    }

    $this->post(route('kiosque.identifier'), ['pin' => '1234'])
        ->assertSessionHasErrors(['pin' => MESSAGE_BLOCAGE_15_MIN]);
});

it('logs the address once when the block starts, never the code', function () {
    Log::spy();

    envoyerCodesFauxAuKiosque($this, 12);

    Log::shouldHaveReceived('warning')
        ->with('Kiosque : identification bloquée après trop de codes erronés.', ['ip' => '127.0.0.1'])
        ->once();
});
