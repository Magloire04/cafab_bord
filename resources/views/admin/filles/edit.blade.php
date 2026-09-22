<x-app-layout>
    <x-slot name="header">
        <h1>Modifier {{ $fille->prenom }} {{ $fille->nom }}</h1>
    </x-slot>

    <form action="{{ route('admin.filles.update', $fille) }}" method="POST">
        @csrf
        @method('PUT')

        <label for="nom">Nom</label>
        <input id="nom" name="nom" type="text" value="{{ old('nom', $fille->nom) }}" required>
        @error('nom') <p>{{ $message }}</p> @enderror

        <label for="prenom">Prénom</label>
        <input id="prenom" name="prenom" type="text" value="{{ old('prenom', $fille->prenom) }}" required>
        @error('prenom') <p>{{ $message }}</p> @enderror

        <label for="contact">Contact</label>
        <input id="contact" name="contact" type="text" value="{{ old('contact', $fille->contact) }}">

        <label for="date_entree">Date d'entrée</label>
        <input id="date_entree" name="date_entree" type="date"
               value="{{ old('date_entree', $fille->date_entree->format('Y-m-d')) }}" required>
        @error('date_entree') <p>{{ $message }}</p> @enderror

        <button type="submit">Enregistrer</button>
    </form>
</x-app-layout>
