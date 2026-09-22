<x-app-layout>
    <x-slot name="header">
        <h1>Registre des filles</h1>
    </x-slot>

    <a href="{{ route('admin.filles.create') }}">Ajouter une fille</a>
    <a href="{{ route('admin.filles.import') }}">Importer depuis Excel</a>

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
                    <td>{{ $fille->pin }}</td>
                    <td>{{ $fille->statut->value }}</td>
                    <td>
                        <a href="{{ route('admin.filles.edit', $fille) }}">Modifier</a>
                        <form action="{{ route('admin.filles.toggle-statut', $fille) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit">
                                {{ $fille->statut->value === 'actif' ? 'Désactiver' : 'Réactiver' }}
                            </button>
                        </form>
                        <form action="{{ route('admin.filles.regenerate-pin', $fille) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit">Régénérer le PIN</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $filles->links() }}
</x-app-layout>
