<x-app-layout>
    <x-slot name="header">
        <h1>Modifier le créneau</h1>
    </x-slot>

    <form action="{{ route('admin.plannings.update', $planning) }}" method="POST">
        @csrf
        @method('PUT')

        <label for="jour_semaine">Jour</label>
        <select id="jour_semaine" name="jour_semaine" required>
            @foreach (\App\Enums\JourSemaine::cases() as $jour)
                <option value="{{ $jour->value }}"
                    {{ old('jour_semaine', $planning->jour_semaine->value) == $jour->value ? 'selected' : '' }}>
                    {{ $jour->libelle() }}
                </option>
            @endforeach
        </select>
        @error('jour_semaine') <p>{{ $message }}</p> @enderror

        <label for="heure_debut">Heure de début</label>
        <input id="heure_debut" name="heure_debut" type="time"
               value="{{ old('heure_debut', \Illuminate\Support\Carbon::parse($planning->heure_debut)->format('H:i')) }}" required>
        @error('heure_debut') <p>{{ $message }}</p> @enderror

        <label for="coach_id">Coach référent</label>
        <select id="coach_id" name="coach_id" required>
            @foreach ($coaches as $coach)
                <option value="{{ $coach->id }}" {{ old('coach_id', $planning->coach_id) == $coach->id ? 'selected' : '' }}>
                    {{ $coach->user->name }}
                </option>
            @endforeach
        </select>
        @error('coach_id') <p>{{ $message }}</p> @enderror

        <button type="submit">Enregistrer</button>
    </form>
</x-app-layout>
