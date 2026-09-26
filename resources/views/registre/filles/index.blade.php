<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Registre des filles</h1>
            <p class="field-hint mb-0">Gérez les comptes et statuts des filles.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('filles.import') }}" class="btn-outline">Importer depuis Excel</a>
            <a href="{{ route('filles.create') }}" class="btn-ink">Ajouter une fille</a>
        </div>
    </x-slot>

    <x-onglets.registre />

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Contact</th>
                    <th>PIN</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filles as $fille)
                    <tr>
                        <td>{{ $fille->nom }}</td>
                        <td>{{ $fille->prenom }}</td>
                        <td>{{ $fille->contact ?? '—' }}</td>
                        <td class="mono">{{ $fille->pin }}</td>
                        <td>
                            @if ($fille->statut->value === 'actif')
                                <span class="badge-st st-heure">{{ $fille->statut->value }}</span>
                            @else
                                <span class="badge-st st-absent">{{ $fille->statut->value }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="{{ route('filles.edit', $fille) }}" class="btn-outline btn-sm">Modifier</a>
                                @can('toggleStatut', $fille)
                                    <form action="{{ route('filles.toggle-statut', $fille) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn-sm {{ $fille->statut->value === 'actif' ? 'btn-danger-outline' : 'btn-success' }}">
                                            {{ $fille->statut->value === 'actif' ? 'Désactiver' : 'Réactiver' }}
                                        </button>
                                    </form>
                                @endcan
                                <form action="{{ route('filles.regenerate-pin', $fille) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn-outline btn-sm">Régénérer le PIN</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $filles->links() }}
    </div>
</x-app-layout>
