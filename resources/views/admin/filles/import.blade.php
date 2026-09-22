<x-app-layout>
    <x-slot name="header">
        <h1>Importer des filles depuis Excel</h1>
    </x-slot>

    <p>Colonnes attendues : nom, prenom, contact (optionnel).</p>

    <form action="{{ route('admin.filles.import.preview') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="file" name="fichier" accept=".xlsx,.xls" required>
        @error('fichier') <p>{{ $message }}</p> @enderror
        <button type="submit">Aperçu</button>
    </form>
</x-app-layout>
