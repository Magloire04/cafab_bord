<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Planning récurrent</h2>
    </x-slot>

    <div class="d-flex justify-content-end mb-4">
        <a href="{{ route('admin.plannings.create') }}" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus-circle me-2"></i>Ajouter un créneau
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Jour</th>
                            <th>Heure</th>
                            <th>Coach référent</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plannings as $planning)
                            <tr>
                                <td>{{ $planning->jour_semaine->libelle() }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($planning->heure_debut)->format('H:i') }}</td>
                                <td>{{ $planning->coach->user->name }}</td>
                                <td>
                                    @if ($planning->actif)
                                        <span class="badge bg-success">Actif</span>
                                    @else
                                        <span class="badge bg-secondary">Inactif</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="{{ route('admin.plannings.edit', $planning) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit me-1"></i>Modifier
                                        </a>
                                        <form action="{{ route('admin.plannings.toggle-actif', $planning) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $planning->actif ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                <i class="fas fa-power-off me-1"></i>{{ $planning->actif ? 'Désactiver' : 'Réactiver' }}
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
</x-app-layout>
