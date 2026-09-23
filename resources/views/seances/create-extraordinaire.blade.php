<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Créer une séance extraordinaire</h2>
    </x-slot>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <form action="{{ route('seances.store-extraordinaire') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="coach_id" class="form-label fw-bold">Coach référent</label>
                            <select id="coach_id" name="coach_id" class="form-select" required>
                                @foreach ($coaches as $coach)
                                    <option value="{{ $coach->id }}" {{ old('coach_id') == $coach->id ? 'selected' : '' }}>
                                        {{ $coach->user->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('coach_id') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="date" class="form-label fw-bold">Date</label>
                            <input id="date" name="date" type="date" class="form-control" value="{{ old('date') }}" required>
                            @error('date') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="heure_prevue" class="form-label fw-bold">Heure prévue</label>
                            <input id="heure_prevue" name="heure_prevue" type="time" class="form-control" value="{{ old('heure_prevue', '17:00') }}" required>
                            @error('heure_prevue') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Créer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
