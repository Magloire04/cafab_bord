<x-app-layout>
    <x-slot name="header">
        <h1>Ajouter un créneau récurrent</h1>
    </x-slot>

    <form action="{{ route('admin.plannings.store') }}" method="POST">
        @csrf

        <label for="jour_semaine">Jour</label>
        <select id="jour_semaine" name="jour_semaine" required>
            @foreach (\App\Enums\JourSemaine::cases() as $jour)
                <option value="{{ $jour->value }}" {{ old('jour_semaine') == $jour->value ? 'selected' : '' }}>
                    {{ $jour->libelle() }}
                </option>
            @endforeach
        </select>
        @error('jour_semaine') <p>{{ $message }}</p> @enderror

        <label for="heure_debut">Heure de début</label>
        <input id="heure_debut" name="heure_debut" type="time" value="{{ old('heure_debut', '17:00') }}" required>
        @error('heure_debut') <p>{{ $message }}</p> @enderror

        <label for="coach_id">Coach référent</label>
        <select id="coach_id" name="coach_id" required>
            @foreach ($coaches as $coach)
                <option value="{{ $coach->id }}" {{ old('coach_id') == $coach->id ? 'selected' : '' }}>
                    {{ $coach->user->name }}
                </option>
            @endforeach
        </select>
        @error('coach_id') <p>{{ $message }}</p> @enderror

        <button type="submit">Créer</button>
    </form>
</x-app-layout>
