<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Planning récurrent</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.plannings.create') }}" class="btn-ink">Ajouter un créneau</a>
        </div>
    </x-slot>

    <div class="table-card">
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
                        <td class="time">{{ \Illuminate\Support\Carbon::parse($planning->heure_debut)->format('H:i') }}</td>
                        <td>{{ $planning->coach->user->name }}</td>
                        <td>
                            @if ($planning->actif)
                                <span class="badge-st st-heure">Actif</span>
                            @else
                                <span class="badge-st st-absent">Inactif</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="{{ route('admin.plannings.edit', $planning) }}" class="btn-outline btn-sm">Modifier</a>
                                <form action="{{ route('admin.plannings.toggle-actif', $planning) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn-sm {{ $planning->actif ? 'btn-danger-outline' : 'btn-success' }}">
                                        {{ $planning->actif ? 'Désactiver' : 'Réactiver' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
