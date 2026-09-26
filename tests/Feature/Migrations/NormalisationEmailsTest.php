<?php

use Illuminate\Support\Facades\DB;

function insereUtilisateurBrut(string $email): int
{
    return DB::table('users')->insertGetId([
        'name' => 'Compte '.$email,
        'email' => $email,
        'password' => 'x',
        'role' => 'coach',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    $this->migration = require database_path('migrations/2026_09_26_000003_normaliser_emails_utilisateurs.php');
});

it('trims and lower-cases every stored email', function () {
    $majuscules = insereUtilisateurBrut('Prudence@CAFAB.bj');
    $espaces = insereUtilisateurBrut('  maurice@cafab.bj ');
    $dejaPropre = insereUtilisateurBrut('admin@cafab.bj');

    $this->migration->up();

    expect(DB::table('users')->where('id', $majuscules)->value('email'))->toBe('prudence@cafab.bj');
    expect(DB::table('users')->where('id', $espaces)->value('email'))->toBe('maurice@cafab.bj');
    expect(DB::table('users')->where('id', $dejaPropre)->value('email'))->toBe('admin@cafab.bj');
});

it('aborts without changing anything when two accounts would end up with the same email', function () {
    $premier = insereUtilisateurBrut('Prudence@CAFAB.bj');
    $second = insereUtilisateurBrut('prudence@cafab.bj ');
    insereUtilisateurBrut('Maurice@CAFAB.bj');
    $avant = DB::table('users')->orderBy('id')->pluck('email')->all();

    expect(fn () => $this->migration->up())->toThrow(
        RuntimeException::class,
        "prudence@cafab.bj : #{$premier} « Prudence@CAFAB.bj », #{$second} « prudence@cafab.bj  »",
    );

    expect(DB::table('users')->orderBy('id')->pluck('email')->all())->toBe($avant);
})->skip(
    fn () => DB::getDriverName() !== 'sqlite',
    "Sous MySQL, l'index unique (collation insensible à la casse) refuse déjà ces doublons.",
);
