<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Validation\Rules\Password;

it('shows validation errors in French', function () {
    $this->from(route('login'))
        ->post(route('login'), ['email' => '', 'password' => ''])
        ->assertSessionHasErrors(['email' => 'Le champ email est obligatoire.']);
});

it('explains the password rule in French', function () {
    $erreur = validator(['password' => 'abc'], ['password' => Password::defaults()])->errors()->first('password');

    expect($erreur)->toContain('au moins 8 caractères');
});

it('writes the password reset email in French', function () {
    $mail = (new ResetPassword('jeton'))->toMail(User::factory()->create());

    expect($mail->subject)->toBe('Réinitialisation de votre mot de passe');
    expect($mail->actionText)->toBe('Réinitialiser le mot de passe');
});
