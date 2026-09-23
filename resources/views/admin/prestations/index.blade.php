<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-0">Prestations</h2>
                <p class="text-muted mb-0">Liste des prestations et de leurs filles affectées.</p>
            </div>
            <a href="{{ route('admin.prestations.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Créer une prestation
            </a>
        </div>
    </x-slot>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Titre</th>
                            <th>Lieu</th>
                            <th>Date</th>
                            <th>Filles affectées</th>
                            <th>Statut</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($prestations as $prestation)
                            <tr>
                                <td class="fw-bold">{{ $prestation->titre }}</td>
                                <td>{{ $prestation->lieu }}</td>
                                <td>{{ $prestation->date->format('d/m/Y') }}</td>
                                <td>{{ $prestation->cachets_count }}</td>
                                <td>
                                    <span class="badge {{ $prestation->statut->value === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $prestation->statut->value }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-2 justify-content-center">
                                        <a href="{{ route('admin.prestations.show', $prestation) }}" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>Voir
                                        </a>
                                        @if ($prestation->statut->value === 'active')
                                            <form action="{{ route('admin.prestations.annuler', $prestation) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="fas fa-ban me-1"></i>Annuler
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Aucune prestation trouvée.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($prestations->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $prestations->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
