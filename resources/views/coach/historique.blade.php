<x-app-layout>
    <x-slot name="header">
        <h1>Mon historique de ponctualité</h1>
    </x-slot>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Arrivée</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pointages as $pointage)
                <tr>
                    <td>{{ $pointage->seance->date->format('d/m/Y') }}</td>
                    <td>{{ $pointage->pointe_a?->format('H:i') ?? '—' }}</td>
                    <td>{{ $pointage->statut_ponctualite->value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $pointages->links() }}
</x-app-layout>
