<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Registre des coachs</h2>
        <p class="text-muted mb-0">Gérez les comptes et statuts des coachs.</p>
    </x-slot>

    <div class="d-flex justify-content-end mb-4">
        <a href="{{ route('admin.coaches.create') }}" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus-circle me-2"></i>Ajouter un coach
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>PIN</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($coaches as $coach)
                            <tr>
                                <td>{{ $coach->user->name }}</td>
                                <td>{{ $coach->user->email }}</td>
                                <td>{{ $coach->contact ?? '—' }}</td>
                                <td>{{ $coach->pin }}</td>
                                <td>
                                    @if ($coach->statut->value === 'actif')
                                        <span class="badge bg-success">{{ $coach->statut->value }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $coach->statut->value }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="{{ route('admin.coaches.edit', $coach) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit me-1"></i>Modifier
                                        </a>
                                        <form action="{{ route('admin.coaches.toggle-statut', $coach) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $coach->statut->value === 'actif' ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                <i class="fas fa-power-off me-1"></i>{{ $coach->statut->value === 'actif' ? 'Désactiver' : 'Réactiver' }}
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.coaches.regenerate-pin', $coach) }}" method="POST" class="d-inline">
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
        {{ $coaches->links() }}
    </div>
</x-app-layout>
