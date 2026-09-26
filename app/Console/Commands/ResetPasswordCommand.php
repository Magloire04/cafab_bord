<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ChangementMotDePasse;
use App\Services\GenerateurMotDePasse;
use App\Support\Email;
use Illuminate\Console\Command;

class ResetPasswordCommand extends Command
{
    protected $signature = 'users:reset-password {email}';

    protected $description = 'Remplace le mot de passe d\'un compte par un mot de passe provisoire, à changer à la prochaine connexion';

    public function handle(GenerateurMotDePasse $generateur, ChangementMotDePasse $changement): int
    {
        $email = Email::normaliser($this->argument('email'));
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Aucun compte ne correspond à l'adresse {$email}.");

            return self::FAILURE;
        }

        $motDePasse = $generateur->generer();
        $changement->imposerProvisoire($user, $motDePasse);

        $this->warn("Mot de passe provisoire pour {$user->name} (affiché une seule fois) : {$motDePasse}");

        return self::SUCCESS;
    }
}
