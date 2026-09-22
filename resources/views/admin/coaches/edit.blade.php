<x-app-layout>
    <x-slot name="header">
        <h1>Modifier {{ $coach->user->name }}</h1>
    </x-slot>

    <form action="{{ route('admin.coaches.update', $coach) }}" method="POST">
        @csrf
        @method('PUT')

        <label for="contact">Contact</label>
        <input id="contact" name="contact" type="text" value="{{ old('contact', $coach->contact) }}">

        <label for="date_entree">Date d'entrée</label>
        <input id="date_entree" name="date_entree" type="date"
               value="{{ old('date_entree', $coach->date_entree->format('Y-m-d')) }}" required>
        @error('date_entree') <p>{{ $message }}</p> @enderror

        <button type="submit">Enregistrer</button>
    </form>
</x-app-layout>
