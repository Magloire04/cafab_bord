<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Historique des pointages</h1>
        </div>
    </x-slot>

    <div class="table-card">
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
                        <td class="time">{{ $pointage->pointe_a?->format('H:i') ?? '—' }}</td>
                        <td>
                            <x-badge-ponctualite :statut="$pointage->statut_ponctualite" />
                        </td>
                        <td>{{ $pointage->source->value }}</td>
                        <td>
                            <form action="{{ route('admin.pointages.corriger', $pointage) }}" method="POST"
                                  class="d-flex flex-column flex-lg-row gap-2 align-items-lg-center">
                                @csrf
                                @method('PATCH')
                                <select name="statut_ponctualite" class="field-select">
                                    <option value="a_l_heure">À l'heure</option>
                                    <option value="en_retard">En retard</option>
                                    <option value="retard_fort">Retard fort</option>
                                    <option value="absent">Absent</option>
                                </select>
                                <input type="number" name="minutes_retard" class="field-control" placeholder="Minutes de retard" min="0">
                                <input type="text" name="motif" class="field-control" placeholder="Motif de la correction" required>
                                <button type="submit" class="btn-outline btn-sm text-nowrap">Corriger</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $pointages->links() }}
    </div>
</x-app-layout>
