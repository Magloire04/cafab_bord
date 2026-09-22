<x-app-layout>
    <x-slot name="header">
        <h1>Calendrier des répétitions — {{ $mois->translatedFormat('F Y') }}</h1>
    </x-slot>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Heure prévue</th>
                <th>Type</th>
                <th>Statut</th>
                <th>Taux de présence</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($seances as $seance)
                <tr>
                    <td>{{ $seance->date->format('d/m/Y') }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($seance->heure_prevue)->format('H:i') }}</td>
                    <td>{{ $seance->type->value }}</td>
                    <td>{{ $seance->statut->value }}</td>
                    <td>{{ $seance->taux_presence !== null ? $seance->taux_presence.' %' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Aucune séance ce mois-ci.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-app-layout>
