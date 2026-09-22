<x-app-layout>
    <x-slot name="header">
        <h1>Planning récurrent</h1>
    </x-slot>

    <a href="{{ route('admin.plannings.create') }}">Ajouter un créneau</a>

    <table>
        <thead>
            <tr>
                <th>Jour</th>
                <th>Heure</th>
                <th>Coach référent</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($plannings as $planning)
                <tr>
                    <td>{{ $planning->jour_semaine->libelle() }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($planning->heure_debut)->format('H:i') }}</td>
                    <td>{{ $planning->coach->user->name }}</td>
                    <td>{{ $planning->actif ? 'Actif' : 'Inactif' }}</td>
                    <td>
                        <a href="{{ route('admin.plannings.edit', $planning) }}">Modifier</a>
                        <form action="{{ route('admin.plannings.toggle-actif', $planning) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit">
                                {{ $planning->actif ? 'Désactiver' : 'Réactiver' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-app-layout>
