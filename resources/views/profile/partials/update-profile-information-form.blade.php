<section>
    <header class="mb-3">
        <h2 class="section-title mb-1">Informations du profil</h2>
        <p class="field-hint mb-0">Votre nom et l'adresse email qui vous sert à vous connecter.</p>
    </header>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="mb-3">
            <label for="name" class="field-label">Nom</label>
            <input id="name" name="name" type="text" class="field-control" value="{{ old('name', $user->name) }}" required autocomplete="name">
            @error('name') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="field-label">Email</label>
            <input id="email" name="email" type="email" class="field-control" value="{{ old('email', $user->email) }}" required autocomplete="username">
            @error('email') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="profil_current_password" class="field-label">Mot de passe actuel</label>
            <x-champ-mot-de-passe id="profil_current_password" name="current_password" autocomplete="current-password" :required="false" />
            <p class="field-hint">Obligatoire seulement si vous changez d'adresse email.</p>
            @error('current_password') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn-ink">Enregistrer</button>
    </form>
</section>
