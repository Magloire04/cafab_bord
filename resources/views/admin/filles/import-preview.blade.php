<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Aperçu de l'import</h2>
    </x-slot>

    <form action="{{ route('admin.filles.import.confirm') }}" method="POST">
        @csrf

        <div class="card shadow-sm mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th></th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Contact</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $index => $row)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="lignes[]" value="{{ $index }}" class="form-check-input"
                                               {{ $row['doublon'] ? '' : 'checked' }}>
                                    </td>
                                    <td>{{ $row['nom'] }}</td>
                                    <td>{{ $row['prenom'] }}</td>
                                    <td>{{ $row['contact'] ?? '—' }}</td>
                                    <td>
                                        @if ($row['doublon'])
                                            <span class="badge bg-warning text-dark">doublon potentiel</span>
                                        @else
                                            <span class="badge bg-success">nouvelle fiche</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check me-2"></i>Confirmer l'import des lignes cochées
            </button>
        </div>
    </form>
</x-app-layout>
