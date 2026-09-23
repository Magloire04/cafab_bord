<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Modifier le créneau</h1>
        </div>
    </x-slot>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="content-card">
                <form action="{{ route('admin.plannings.update', $planning) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="jour_semaine" class="field-label">Jour</label>
                        <select id="jour_semaine" name="jour_semaine" class="field-select" required>
                            @foreach (\App\Enums\JourSemaine::cases() as $jour)
                                <option value="{{ $jour->value }}"
                                    {{ old('jour_semaine', $planning->jour_semaine->value) == $jour->value ? 'selected' : '' }}>
                                    {{ $jour->libelle() }}
                                </option>
                            @endforeach
                        </select>
                        @error('jour_semaine') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="heure_debut" class="field-label">Heure de début</label>
                        <input id="heure_debut" name="heure_debut" type="time" class="field-control"
                               value="{{ old('heure_debut', \Illuminate\Support\Carbon::parse($planning->heure_debut)->format('H:i')) }}" required>
                        @error('heure_debut') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="coach_id" class="field-label">Coach référent</label>
                        <select id="coach_id" name="coach_id" class="field-select" required>
                            @foreach ($coaches as $coach)
                                <option value="{{ $coach->id }}" {{ old('coach_id', $planning->coach_id) == $coach->id ? 'selected' : '' }}>
                                    {{ $coach->user->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('coach_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn-ink justify-content-center">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
