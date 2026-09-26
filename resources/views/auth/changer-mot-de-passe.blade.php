<x-guest-layout>
    <h1 class="page-title">Choisissez votre mot de passe</h1>
    <p class="field-hint mt-2 mb-0">
        Votre compte utilise un mot de passe provisoire. Choisissez-en un nouveau pour continuer :
        au moins 8 caractères, avec des majuscules, des minuscules et des chiffres.
    </p>

    <form method="POST" action="{{ route('password.changer.update') }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="password" class="field-label">Nouveau mot de passe</label>
            <x-champ-mot-de-passe id="password" name="password" autocomplete="new-password" />
            @error('password') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="field-label">Confirmer le mot de passe</label>
            <x-champ-mot-de-passe id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
        </div>

        <button type="submit" class="btn-ink w-100 justify-content-center">Enregistrer et continuer</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
        @csrf
        <button type="submit" class="field-hint border-0 bg-transparent p-0">Se déconnecter</button>
    </form>
</x-guest-layout>
