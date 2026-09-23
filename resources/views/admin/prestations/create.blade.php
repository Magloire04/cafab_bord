<x-app-layout>
    <x-slot name="header">
        <h1>Créer une prestation</h1>
    </x-slot>

    <form action="{{ route('admin.prestations.store') }}" method="POST">
        @csrf

        <label for="titre">Titre</label>
        <input id="titre" name="titre" type="text" value="{{ old('titre') }}" required>
        @error('titre') <p>{{ $message }}</p> @enderror

        <label for="lieu">Lieu</label>
        <input id="lieu" name="lieu" type="text" value="{{ old('lieu') }}" required>
        @error('lieu') <p>{{ $message }}</p> @enderror

        <label for="date">Date</label>
        <input id="date" name="date" type="date" value="{{ old('date') }}" required>
        @error('date') <p>{{ $message }}</p> @enderror

        <label for="montant_defaut">Montant par défaut du cachet</label>
        <input id="montant_defaut" name="montant_defaut" type="number" step="0.01" value="{{ old('montant_defaut') }}" required>
        @error('montant_defaut') <p>{{ $message }}</p> @enderror

        <fieldset>
            <legend>Filles participantes</legend>
            @foreach ($filles as $fille)
                <div>
                    <label>
                        <input type="checkbox" name="fille_ids[]" value="{{ $fille->id }}">
                        {{ $fille->prenom }} {{ $fille->nom }}
                    </label>
                    <label>
                        Montant (optionnel, sinon le montant par défaut)
                        <input type="number" step="0.01" name="montants[{{ $fille->id }}]">
                    </label>
                </div>
            @endforeach
        </fieldset>
        @error('fille_ids') <p>{{ $message }}</p> @enderror

        <button type="submit">Créer</button>
    </form>
</x-app-layout>
