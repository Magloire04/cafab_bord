<x-guest-layout>
    <h1 class="page-title">Connexion</h1>

    <x-auth-session-status class="mt-3" :status="session('status')" />

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
            <x-champ-mot-de-passe id="password" name="password" autocomplete="current-password" placeholder="••••••••" />
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
