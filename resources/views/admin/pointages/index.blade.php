<x-app-layout>
    <x-slot name="header">
        <h1>Historique des pointages</h1>
    </x-slot>

    <table>
        <thead>
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
                    <td>{{ $pointage->statut_ponctualite->value }}</td>
                    <td>{{ $pointage->source->value }}</td>
                    <td>
                        <form action="{{ route('admin.pointages.corriger', $pointage) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <select name="statut_ponctualite">
                                <option value="a_l_heure">À l'heure</option>
                                <option value="en_retard">En retard</option>
                                <option value="retard_fort">Retard fort</option>
                                <option value="absent">Absent</option>
                            </select>
                            <input type="number" name="minutes_retard" placeholder="Minutes de retard" min="0">
                            <input type="text" name="motif" placeholder="Motif de la correction" required>
                            <button type="submit">Corriger</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $pointages->links() }}
</x-app-layout>
