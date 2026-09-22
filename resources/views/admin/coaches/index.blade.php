<x-app-layout>
    <x-slot name="header">
        <h1>Registre des coachs</h1>
    </x-slot>

    <a href="{{ route('admin.coaches.create') }}">Ajouter un coach</a>

    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Email</th>
                <th>Contact</th>
                <th>PIN</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($coaches as $coach)
                <tr>
                    <td>{{ $coach->user->name }}</td>
                    <td>{{ $coach->user->email }}</td>
                    <td>{{ $coach->contact ?? '—' }}</td>
                    <td>{{ $coach->pin }}</td>
                    <td>{{ $coach->statut->value }}</td>
                    <td>
                        <a href="{{ route('admin.coaches.edit', $coach) }}">Modifier</a>
                        <form action="{{ route('admin.coaches.toggle-statut', $coach) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit">
                                {{ $coach->statut->value === 'actif' ? 'Désactiver' : 'Réactiver' }}
                            </button>
                        </form>
                        <form action="{{ route('admin.coaches.regenerate-pin', $coach) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit">Régénérer le PIN</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $coaches->links() }}
</x-app-layout>
