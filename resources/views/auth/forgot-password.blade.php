<x-guest-layout>
    <h1 class="page-title">Mot de passe oublié</h1>

    <p class="field-hint mt-2 mb-0">
        Indiquez votre adresse email : si un compte y correspond, vous recevrez un lien pour choisir un nouveau mot de passe.
        En cas de problème, contactez l'administrateur.
    </p>

    <x-auth-session-status class="field-hint mt-2" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="field-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                class="field-control" required autofocus>
            @error('email') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn-ink w-100 justify-content-center">
            Envoyer le lien de réinitialisation
        </button>
    </form>
</x-guest-layout>
