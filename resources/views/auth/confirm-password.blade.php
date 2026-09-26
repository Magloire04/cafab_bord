<x-guest-layout>
    <h1 class="page-title">Confirmer le mot de passe</h1>

    <p class="field-hint mt-2 mb-0">
        Cette zone de l'application est protégée. Confirme ton mot de passe pour continuer.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="mb-3">
            <label for="password" class="field-label">Mot de passe</label>
            <input id="password" name="password" type="password"
                class="field-control" required autocomplete="current-password">
            @error('password') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn-ink w-100 justify-content-center">
            Confirmer
        </button>
    </form>
</x-guest-layout>
