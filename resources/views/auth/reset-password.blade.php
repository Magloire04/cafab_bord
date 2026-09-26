<x-guest-layout>
    <h1 class="page-title">Réinitialiser le mot de passe</h1>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="mb-3">
            <label for="email" class="field-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}"
                class="field-control" required autofocus autocomplete="username">
            @error('email') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="field-label">Mot de passe</label>
            <x-champ-mot-de-passe id="password" name="password" autocomplete="new-password" />
            @error('password') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="field-label">Confirmer le mot de passe</label>
            <x-champ-mot-de-passe id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
            @error('password_confirmation') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn-ink w-100 justify-content-center">
            Réinitialiser le mot de passe
        </button>
    </form>
</x-guest-layout>
