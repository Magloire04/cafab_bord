<x-app-layout>
    <x-slot name="header">
        <h1>Aperçu de l'import</h1>
    </x-slot>

    <form action="{{ route('admin.filles.import.confirm') }}" method="POST">
        @csrf

        <table>
            <thead>
                <tr>
                    <th></th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Contact</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $index => $row)
                    <tr>
                        <td>
                            <input type="checkbox" name="lignes[]" value="{{ $index }}"
                                   {{ $row['doublon'] ? '' : 'checked' }}>
                        </td>
                        <td>{{ $row['nom'] }}</td>
                        <td>{{ $row['prenom'] }}</td>
                        <td>{{ $row['contact'] ?? '—' }}</td>
                        <td>{{ $row['doublon'] ? 'doublon potentiel' : 'nouvelle fiche' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <button type="submit">Confirmer l'import des lignes cochées</button>
    </form>
</x-app-layout>
