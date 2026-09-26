<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Ajouter un créneau récurrent</h1>
        </div>
    </x-slot>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="content-card">
                <form action="{{ route('plannings.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="jour_semaine" class="field-label">Jour</label>
                        <select id="jour_semaine" name="jour_semaine" class="field-select" required>
                            @foreach (\App\Enums\JourSemaine::cases() as $jour)
                                <option value="{{ $jour->value }}" {{ old('jour_semaine') == $jour->value ? 'selected' : '' }}>
                                    {{ $jour->libelle() }}
                                </option>
                            @endforeach
                        </select>
                        @error('jour_semaine') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="heure_debut" class="field-label">Heure de début</label>
                        <input id="heure_debut" name="heure_debut" type="time" class="field-control" value="{{ old('heure_debut', '17:00') }}" required>
                        @error('heure_debut') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    @if (auth()->user()->role === \App\Enums\UserRole::Admin)
                        <div class="mb-4">
                            <label for="coach_id" class="field-label">Coach référent</label>
                            <select id="coach_id" name="coach_id" class="field-select" required>
                                @foreach ($coaches as $coach)
                                    <option value="{{ $coach->id }}" {{ old('coach_id') == $coach->id ? 'selected' : '' }}>
                                        {{ $coach->user->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('coach_id') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div class="mb-4">
                            <label for="coach_referent" class="field-label">Coach référent</label>
                            <input id="coach_referent" type="text" class="field-control" value="{{ auth()->user()->name }}" disabled>
                            <p class="field-hint">Vos créneaux sont toujours à votre nom.</p>
                        </div>
                    @endif

                    <div class="d-grid">
                        <button type="submit" class="btn-ink justify-content-center">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
