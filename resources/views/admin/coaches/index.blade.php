<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Registre des coachs</h1>
            <p class="field-hint mb-0">Gérez les comptes et statuts des coachs.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.coaches.create') }}" class="btn-ink">Ajouter un coach</a>
        </div>
    </x-slot>

    <x-onglets.registre />

    <div class="table-card">
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
                        <td class="mono">{{ $coach->pin }}</td>
                        <td>
                            @if ($coach->statut->value === 'actif')
                                <span class="badge-st st-heure">{{ $coach->statut->value }}</span>
                            @else
                                <span class="badge-st st-absent">{{ $coach->statut->value }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="{{ route('admin.coaches.edit', $coach) }}" class="btn-outline btn-sm">Modifier</a>
                                <form action="{{ route('admin.coaches.toggle-statut', $coach) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn-sm {{ $coach->statut->value === 'actif' ? 'btn-danger-outline' : 'btn-success' }}">
                                        {{ $coach->statut->value === 'actif' ? 'Désactiver' : 'Réactiver' }}
                                    </button>
                                </form>
                                <form action="{{ route('admin.coaches.regenerate-pin', $coach) }}" method="POST" class="d-inline">
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
        {{ $coaches->links() }}
    </div>
</x-app-layout>
