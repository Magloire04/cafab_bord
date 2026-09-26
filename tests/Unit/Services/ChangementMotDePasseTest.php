<?php

use App\Models\User;
use App\Services\ChangementMotDePasse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function inserePlusieursSessions(User $user, User $autre): void
{
    DB::table('sessions')->insert([
        ['id' => 'autre-navigateur', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'session-courante', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'autre-compte', 'user_id' => $autre->id, 'payload' => '', 'last_activity' => now()->timestamp],
    ]);
}

beforeEach(fn () => config(['session.driver' => 'database']));

it('sets a chosen password, clears the flag and closes the other sessions', function () {
    $user = User::factory()->motDePasseProvisoire()->create(['remember_token' => 'ancien-jeton']);
    inserePlusieursSessions($user, User::factory()->create());

    app(ChangementMotDePasse::class)->definir($user, 'Nouveau-Pass1', 'session-courante');

    $user->refresh();
    expect(Hash::check('Nouveau-Pass1', $user->password))->toBeTrue();
    expect($user->must_change_password)->toBeFalse();
    expect($user->remember_token)->not->toBe('ancien-jeton');
    expect(DB::table('sessions')->pluck('id')->all())->toEqualCanonicalizing(['session-courante', 'autre-compte']);
});

it('imposes a provisional password and closes every session of the account', function () {
    $user = User::factory()->create();
    inserePlusieursSessions($user, User::factory()->create());

    app(ChangementMotDePasse::class)->imposerProvisoire($user, 'Provisoire-7!');

    $user->refresh();
    expect(Hash::check('Provisoire-7!', $user->password))->toBeTrue();
    expect($user->must_change_password)->toBeTrue();
    expect(DB::table('sessions')->pluck('id')->all())->toBe(['autre-compte']);
});
