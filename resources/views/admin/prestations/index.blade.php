<x-app-layout>
    <x-slot name="header">
        <h1>Prestations</h1>
    </x-slot>

    <a href="{{ route('admin.prestations.create') }}">Créer une prestation</a>

    <table>
        <thead>
            <tr>
                <th>Titre</th>
                <th>Lieu</th>
                <th>Date</th>
                <th>Filles affectées</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($prestations as $prestation)
                <tr>
                    <td>{{ $prestation->titre }}</td>
                    <td>{{ $prestation->lieu }}</td>
                    <td>{{ $prestation->date->format('d/m/Y') }}</td>
                    <td>{{ $prestation->cachets_count }}</td>
                    <td>{{ $prestation->statut->value }}</td>
                    <td>
                        <a href="{{ route('admin.prestations.show', $prestation) }}">Voir</a>
                        @if ($prestation->statut->value === 'active')
                            <form action="{{ route('admin.prestations.annuler', $prestation) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit">Annuler</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $prestations->links() }}
</x-app-layout>
