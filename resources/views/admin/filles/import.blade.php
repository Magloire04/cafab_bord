<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Importer des filles depuis Excel</h2>
    </x-slot>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <p class="text-muted">Colonnes attendues : nom, prenom, contact (optionnel).</p>

                    <form action="{{ route('admin.filles.import.preview') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <input type="file" name="fichier" accept=".xlsx,.xls" class="form-control" required>
                            @error('fichier') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-eye me-2"></i>Aperçu
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
