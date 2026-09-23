<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Historique des pointages</h2>
    </x-slot>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Personne</th>
                            <th>Arrivée</th>
                            <th>Statut</th>
                            <th>Source</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pointages as $pointage)
                            <tr>
                                <td>{{ $pointage->seance->date->format('d/m/Y') }}</td>
                                <td>
                                    @if ($pointage->pointable_type === \App\Models\Fille::class)
                                        {{ $pointage->pointable->prenom }} {{ $pointage->pointable->nom }}
                                    @else
                                        {{ $pointage->pointable->user->name }}
                                    @endif
                                </td>
                                <td>{{ $pointage->pointe_a?->format('H:i') ?? '—' }}</td>
                                <td>
                                    @if ($pointage->statut_ponctualite->value === 'a_l_heure')
                                        <span class="badge bg-success">{{ $pointage->statut_ponctualite->value }}</span>
                                    @elseif ($pointage->statut_ponctualite->value === 'en_retard')
                                        <span class="badge bg-warning text-dark">{{ $pointage->statut_ponctualite->value }}</span>
                                    @elseif ($pointage->statut_ponctualite->value === 'retard_fort')
                                        <span class="badge bg-danger">{{ $pointage->statut_ponctualite->value }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $pointage->statut_ponctualite->value }}</span>
                                    @endif
                                </td>
                                <td>{{ $pointage->source->value }}</td>
                                <td>
                                    <form action="{{ route('admin.pointages.corriger', $pointage) }}" method="POST"
                                          class="d-flex flex-column flex-lg-row gap-2 align-items-lg-center">
                                        @csrf
                                        @method('PATCH')
                                        <select name="statut_ponctualite" class="form-select form-select-sm">
                                            <option value="a_l_heure">À l'heure</option>
                                            <option value="en_retard">En retard</option>
                                            <option value="retard_fort">Retard fort</option>
                                            <option value="absent">Absent</option>
                                        </select>
                                        <input type="number" name="minutes_retard" class="form-control form-control-sm" placeholder="Minutes de retard" min="0">
                                        <input type="text" name="motif" class="form-control form-control-sm" placeholder="Motif de la correction" required>
                                        <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">
                                            <i class="fas fa-check me-1"></i>Corriger
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4">
        {{ $pointages->links() }}
    </div>
</x-app-layout>
