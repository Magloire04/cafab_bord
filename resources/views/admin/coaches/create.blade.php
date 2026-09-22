<x-app-layout>
    <x-slot name="header">
        <h1>Ajouter un coach</h1>
    </x-slot>

    <form action="{{ route('admin.coaches.store') }}" method="POST">
        @csrf

        <label for="name">Nom complet</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" required>
        @error('name') <p>{{ $message }}</p> @enderror

        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required>
        @error('email') <p>{{ $message }}</p> @enderror

        <label for="contact">Contact</label>
        <input id="contact" name="contact" type="text" value="{{ old('contact') }}">

        <label for="date_entree">Date d'entrée</label>
        <input id="date_entree" name="date_entree" type="date" value="{{ old('date_entree') }}" required>
        @error('date_entree') <p>{{ $message }}</p> @enderror

        <button type="submit">Créer</button>
    </form>
</x-app-layout>
