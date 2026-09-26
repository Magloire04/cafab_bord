<section>
    <header class="mb-3">
        <h2 class="section-title mb-1">Mot de passe</h2>
        <p class="field-hint mb-0">
            Au moins 8 caractères, avec des majuscules, des minuscules et des chiffres.
            Changer de mot de passe déconnecte vos autres appareils.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        @method('put')

        <div class="mb-3">
            <label for="update_password_current_password" class="field-label">Mot de passe actuel</label>
            <x-champ-mot-de-passe id="update_password_current_password" name="current_password" autocomplete="current-password" />
            @error('current_password', 'updatePassword') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="update_password_password" class="field-label">Nouveau mot de passe</label>
            <x-champ-mot-de-passe id="update_password_password" name="password" autocomplete="new-password" />
            @error('password', 'updatePassword') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="update_password_password_confirmation" class="field-label">Confirmer le nouveau mot de passe</label>
            <x-champ-mot-de-passe id="update_password_password_confirmation" name="password_confirmation" autocomplete="new-password" />
            @error('password_confirmation', 'updatePassword') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn-ink">Changer le mot de passe</button>
    </form>
</section>
