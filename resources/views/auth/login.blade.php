<x-guest-layout>
    <div class="logo-box">
        <img src="{{ asset('images/logo-cafab.png') }}" alt="CAFAB">
    </div>

    <p class="overline mb-1">Présence &amp; paiements</p>
    <h1 class="page-title">Connexion</h1>

    <x-auth-session-status class="field-hint mt-2" :status="session('status')" />

    @if ($errors->any())
        <div class="callout-danger mt-3">
            <span class="fw-semibold">{{ $errors->first() }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="field-label">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                class="field-control" placeholder="nom@cafab.bj"
                required autofocus autocomplete="username">
        </div>

        <div class="mb-3">
            <label for="password" class="field-label">Mot de passe</label>
            <div class="d-flex gap-2">
                <input type="password" name="password" id="password"
                    class="field-control" placeholder="••••••••"
                    required autocomplete="current-password">
                <button class="btn-outline js-toggle-password" type="button" title="Afficher le mot de passe">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <div class="mb-3 d-flex align-items-center gap-2">
            <input class="field-check" type="checkbox" id="remember_me" name="remember">
            <label for="remember_me" class="field-hint mb-0">Se souvenir de moi</label>
        </div>

        <button type="submit" class="btn-ink w-100 justify-content-center">
            Se connecter
        </button>

        @if (Route::has('password.request'))
            <a class="d-block mt-3 text-center field-hint text-decoration-none" href="{{ route('password.request') }}">
                Mot de passe oublié ?
            </a>
        @endif
    </form>
</x-guest-layout>
