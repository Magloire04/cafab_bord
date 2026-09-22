<x-app-layout>
    <x-slot name="header">
        <h1>Ajouter une fille</h1>
    </x-slot>

    <form action="{{ route('admin.filles.store') }}" method="POST">
        @csrf

        <label for="nom">Nom</label>
        <input id="nom" name="nom" type="text" value="{{ old('nom') }}" required>
        @error('nom') <p>{{ $message }}</p> @enderror

        <label for="prenom">Prénom</label>
        <input id="prenom" name="prenom" type="text" value="{{ old('prenom') }}" required>
        @error('prenom') <p>{{ $message }}</p> @enderror

        <label for="contact">Contact</label>
        <input id="contact" name="contact" type="text" value="{{ old('contact') }}">

        <label for="date_entree">Date d'entrée</label>
        <input id="date_entree" name="date_entree" type="date" value="{{ old('date_entree') }}" required>
        @error('date_entree') <p>{{ $message }}</p> @enderror

        <button type="submit">Créer</button>
    </form>
</x-app-layout>
