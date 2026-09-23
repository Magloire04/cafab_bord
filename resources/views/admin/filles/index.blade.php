<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Registre des filles</h2>
        <p class="text-muted mb-0">Gérez les comptes et statuts des filles.</p>
    </x-slot>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="{{ route('admin.filles.import') }}" class="btn btn-outline-secondary shadow-sm">
            <i class="fas fa-file-excel me-2"></i>Importer depuis Excel
        </a>
        <a href="{{ route('admin.filles.create') }}" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus-circle me-2"></i>Ajouter une fille
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Contact</th>
                            <th>PIN</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filles as $fille)
                            <tr>
                                <td>{{ $fille->nom }}</td>
                                <td>{{ $fille->prenom }}</td>
                                <td>{{ $fille->contact ?? '—' }}</td>
                                <td>{{ $fille->pin }}</td>
                                <td>
                                    @if ($fille->statut->value === 'actif')
                                        <span class="badge bg-success">{{ $fille->statut->value }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $fille->statut->value }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="{{ route('admin.filles.edit', $fille) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit me-1"></i>Modifier
                                        </a>
                                        <form action="{{ route('admin.filles.toggle-statut', $fille) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $fille->statut->value === 'actif' ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                <i class="fas fa-power-off me-1"></i>{{ $fille->statut->value === 'actif' ? 'Désactiver' : 'Réactiver' }}
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.filles.regenerate-pin', $fille) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-key me-1"></i>Régénérer le PIN
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4">
        {{ $filles->links() }}
    </div>
</x-app-layout>
