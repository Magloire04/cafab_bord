<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Créer une séance extraordinaire</h1>
        </div>
    </x-slot>

    <x-onglets.planning />

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="content-card">
                <form action="{{ route('seances.store-extraordinaire') }}" method="POST">
                    @csrf

                    <div class="mb-3">
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

                    <div class="mb-3">
                        <label for="date" class="field-label">Date</label>
                        <input id="date" name="date" type="date" class="field-control" value="{{ old('date') }}" required>
                        @error('date') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="heure_prevue" class="field-label">Heure prévue</label>
                        <input id="heure_prevue" name="heure_prevue" type="time" class="field-control" value="{{ old('heure_prevue', '17:00') }}" required>
                        @error('heure_prevue') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn-ink justify-content-center">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
