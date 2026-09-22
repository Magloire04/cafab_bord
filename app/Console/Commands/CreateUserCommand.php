<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateUserCommand extends Command
{
    protected $signature = 'users:create {name} {email} {--role=coach}';

    protected $description = 'Crée un compte Admin ou Coach avec un mot de passe généré';

    public function handle(): int
    {
        $data = [
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
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

        $password = Str::password(16);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => UserRole::from($data['role']),
            'password' => Hash::make($password),
        ]);

        $this->warn("Compte créé. Mot de passe (à noter, affiché une seule fois) : {$password}");

        return self::SUCCESS;
    }
}
