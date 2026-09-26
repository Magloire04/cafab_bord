<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Chaque recherche de compte passe désormais par une adresse en minuscules
 * (App\Support\Email::normaliser). Sur SQLite la comparaison tient compte de
 * la casse : une adresse enregistrée « Prudence@CAFAB.bj » ne se connecterait
 * plus. On normalise donc les adresses existantes, la même transformation
 * étant recopiée ici pour que la migration ne change pas si le code évolue.
 */
return new class extends Migration
{
    public function up(): void
    {
        $utilisateurs = DB::table('users')->orderBy('id')->get(['id', 'email']);
        $normaliser = fn (string $email): string => Str::lower(trim($email));

        // Deux comptes qui deviendraient identiques : on s'arrête avant toute
        // écriture, la fusion des comptes est une décision humaine.
        $collisions = $utilisateurs
            ->groupBy(fn (object $utilisateur) => $normaliser($utilisateur->email))
            ->filter(fn ($groupe) => $groupe->count() > 1);

        if ($collisions->isNotEmpty()) {
            $details = $collisions
                ->map(fn ($groupe, string $adresse) => $adresse.' : '.$groupe
                    ->map(fn (object $utilisateur) => "#{$utilisateur->id} « {$utilisateur->email} »")
                    ->implode(', '))
                ->implode(' ; ');

            throw new RuntimeException(
                'Normalisation des e-mails interrompue, aucun compte modifié : ces comptes auraient la même adresse '
                .'une fois en minuscules. Fusionnez-les ou corrigez-les, puis relancez la migration. '.$details
            );
        }

        DB::transaction(function () use ($utilisateurs, $normaliser) {
            foreach ($utilisateurs as $utilisateur) {
                $email = $normaliser($utilisateur->email);

                if ($email !== $utilisateur->email) {
                    DB::table('users')->where('id', $utilisateur->id)->update(['email' => $email]);
                }
            }
        });
    }

    public function down(): void
    {
        // Sans effet : la casse et les espaces d'origine ne sont pas conservés,
        // et une adresse en minuscules reste valable pour la connexion.
    }
};
