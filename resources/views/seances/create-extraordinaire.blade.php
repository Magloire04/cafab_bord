<x-app-layout>
    <x-slot name="header">
        <h1>Créer une séance extraordinaire</h1>
    </x-slot>

    <form action="{{ route('seances.store-extraordinaire') }}" method="POST">
        @csrf

        <label for="coach_id">Coach référent</label>
        <select id="coach_id" name="coach_id" required>
            @foreach ($coaches as $coach)
                <option value="{{ $coach->id }}" {{ old('coach_id') == $coach->id ? 'selected' : '' }}>
                    {{ $coach->user->name }}
                </option>
            @endforeach
        </select>
        @error('coach_id') <p>{{ $message }}</p> @enderror

        <label for="date">Date</label>
        <input id="date" name="date" type="date" value="{{ old('date') }}" required>
        @error('date') <p>{{ $message }}</p> @enderror

        <label for="heure_prevue">Heure prévue</label>
        <input id="heure_prevue" name="heure_prevue" type="time" value="{{ old('heure_prevue', '17:00') }}" required>
        @error('heure_prevue') <p>{{ $message }}</p> @enderror

        <button type="submit">Créer</button>
    </form>
</x-app-layout>
