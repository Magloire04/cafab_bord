<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\GenerateurMotDePasse;
use App\Support\Email;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateUserCommand extends Command
{
    protected $signature = 'users:create {name} {email} {--role=coach}';

    protected $description = 'Crée un compte Admin ou Coach avec un mot de passe généré';

    public function handle(GenerateurMotDePasse $generateur): int
    {
        $data = [
            'name' => $this->argument('name'),
            'email' => Email::normaliser($this->argument('email')),
            'role' => $this->option('role'),
        ];

        try {
            validator($data, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
                'role' => ['required', Rule::enum(UserRole::class)],
            ])->validate();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->error("{$field} : ".implode(' ', $messages));
            }

            return self::FAILURE;
        }

        $password = $generateur->generer();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => UserRole::from($data['role']),
            'password' => Hash::make($password),
            'must_change_password' => true,
        ]);

        $this->warn("Compte créé. Mot de passe provisoire (affiché une seule fois, à changer à la première connexion) : {$password}");

        return self::SUCCESS;
    }
}
