<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Modifier {{ $fille->prenom }} {{ $fille->nom }}</h2>
    </x-slot>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <form action="{{ route('admin.filles.update', $fille) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="nom" class="form-label fw-bold">Nom</label>
                            <input id="nom" name="nom" type="text" class="form-control" value="{{ old('nom', $fille->nom) }}" required>
                            @error('nom') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="prenom" class="form-label fw-bold">Prénom</label>
                            <input id="prenom" name="prenom" type="text" class="form-control" value="{{ old('prenom', $fille->prenom) }}" required>
                            @error('prenom') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="contact" class="form-label fw-bold">Contact</label>
                            <input id="contact" name="contact" type="text" class="form-control" value="{{ old('contact', $fille->contact) }}">
                        </div>

                        <div class="mb-4">
                            <label for="date_entree" class="form-label fw-bold">Date d'entrée</label>
                            <input id="date_entree" name="date_entree" type="date" class="form-control"
                                   value="{{ old('date_entree', $fille->date_entree->format('Y-m-d')) }}" required>
                            @error('date_entree') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
