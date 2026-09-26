<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Mon historique de ponctualité</h1>
        </div>
    </x-slot>

    <div class="table-card mb-3">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Arrivée</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pointages as $pointage)
                    @php
                        $minutesRetard = in_array($pointage->statut_ponctualite->value, ['en_retard'])
                            ? $pointage->minutes_retard
                            : null;
                    @endphp
                    <tr>
                        <td class="fw-bold">{{ $pointage->seance->date->format('d/m/Y') }}</td>
                        <td class="time">{{ $pointage->pointe_a?->format('H:i') ?? '—' }}</td>
                        <td>
                            <x-badge-ponctualite :statut="$pointage->statut_ponctualite" :minutes="$minutesRetard" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center py-4 field-hint">Aucun historique de pointage pour le moment.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($pointages->hasPages())
        <div>
            {{ $pointages->links('pagination::bootstrap-5') }}
        </div>
    @endif
</x-app-layout>
