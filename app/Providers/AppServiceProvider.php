<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Règle de tout mot de passe choisi par un utilisateur (profil, réinitialisation, changement obligatoire).
        Password::defaults(fn () => Password::min(8)->letters()->mixedCase()->numbers());
    }
}
