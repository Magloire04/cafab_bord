<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Mon historique de ponctualité</h2>
    </x-slot>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Arrivée</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pointages as $pointage)
                            <tr>
                                <td class="fw-bold">{{ $pointage->seance->date->format('d/m/Y') }}</td>
                                <td>{{ $pointage->pointe_a?->format('H:i') ?? '—' }}</td>
                                <td>
                                    @php
                                        $ponctualiteBadgeClass = match ($pointage->statut_ponctualite->value) {
                                            'a_l_heure' => 'bg-success',
                                            'en_retard' => 'bg-warning text-dark',
                                            'retard_fort' => 'bg-danger',
                                            'absent' => 'bg-secondary',
                                            default => 'bg-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $ponctualiteBadgeClass }}">{{ $pointage->statut_ponctualite->value }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">Aucun historique de pointage pour le moment.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($pointages->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $pointages->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
