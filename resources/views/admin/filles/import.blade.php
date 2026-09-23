<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Importer des filles depuis Excel</h1>
        </div>
    </x-slot>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="content-card">
                <p class="field-hint">Colonnes attendues : nom, prenom, contact (optionnel).</p>

                <form action="{{ route('admin.filles.import.preview') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <input type="file" name="fichier" accept=".xlsx,.xls" class="field-control" required>
                        @error('fichier') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn-ink justify-content-center">Aperçu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
