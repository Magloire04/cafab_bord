<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Ajouter un coach</h2>
    </x-slot>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <form action="{{ route('admin.coaches.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold">Nom complet</label>
                            <input id="name" name="name" type="text" class="form-control" value="{{ old('name') }}" required>
                            @error('name') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold">E-mail</label>
                            <input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" required>
                            @error('email') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="contact" class="form-label fw-bold">Contact</label>
                            <input id="contact" name="contact" type="text" class="form-control" value="{{ old('contact') }}">
                        </div>

                        <div class="mb-4">
                            <label for="date_entree" class="form-label fw-bold">Date d'entrée</label>
                            <input id="date_entree" name="date_entree" type="date" class="form-control" value="{{ old('date_entree') }}" required>
                            @error('date_entree') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
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
