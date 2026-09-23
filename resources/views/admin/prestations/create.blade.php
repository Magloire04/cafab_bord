<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Créer une prestation</h2>
        <p class="text-muted mb-0">Renseigne les informations de la prestation et affecte les filles participantes.</p>
    </x-slot>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form action="{{ route('admin.prestations.store') }}" method="POST">
                @csrf

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="titre" class="form-label fw-bold">Titre</label>
                        <input id="titre" name="titre" type="text" class="form-control" value="{{ old('titre') }}" required>
                        @error('titre') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="lieu" class="form-label fw-bold">Lieu</label>
                        <input id="lieu" name="lieu" type="text" class="form-control" value="{{ old('lieu') }}" required>
                        @error('lieu') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="date" class="form-label fw-bold">Date</label>
                        <input id="date" name="date" type="date" class="form-control" value="{{ old('date') }}" required>
                        @error('date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="montant_defaut" class="form-label fw-bold">Montant par défaut du cachet</label>
                        <input id="montant_defaut" name="montant_defaut" type="number" step="0.01" class="form-control" value="{{ old('montant_defaut') }}" required>
                        @error('montant_defaut') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>

                <fieldset class="mb-2">
                    <legend class="fs-6 fw-bold"><i class="fas fa-users me-2 text-primary"></i>Filles participantes</legend>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">Affecter</th>
                                    <th>Fille</th>
                                    <th>Montant (optionnel, sinon le montant par défaut)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filles as $fille)
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input" name="fille_ids[]" value="{{ $fille->id }}">
                                        </td>
                                        <td>{{ $fille->prenom }} {{ $fille->nom }}</td>
                                        <td>
                                            <input type="number" step="0.01" name="montants[{{ $fille->id }}]" class="form-control form-control-sm">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </fieldset>
                @error('fille_ids') <div class="text-danger small mb-3">{{ $message }}</div> @enderror

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Créer
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
