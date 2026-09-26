<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Planning récurrent</h1>
        </div>
        @can('create', \App\Models\PlanningRepetition::class)
            <div class="d-flex gap-2">
                <a href="{{ route('plannings.create') }}" class="btn-ink">Ajouter un créneau</a>
            </div>
        @endcan
    </x-slot>

    <x-onglets.planning />

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Jour</th>
                    <th>Heure</th>
                    <th>Coach référent</th>
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
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
                        <td class="text-end">
                            @can('update', $planning)
                                <x-menu-actions>
                                    <li><a class="dropdown-item" href="{{ route('plannings.edit', $planning) }}">Modifier</a></li>
                                    <li>
                                        <form action="{{ route('plannings.toggle-actif', $planning) }}" method="POST"
                                              data-confirmer="{{ $planning->actif ? 'Désactiver' : 'Réactiver' }} le créneau du {{ $planning->jour_semaine->libelle() }} ?">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="dropdown-item">{{ $planning->actif ? 'Désactiver' : 'Réactiver' }}</button>
                                        </form>
                                    </li>
                                </x-menu-actions>
                            @else
                                <span class="field-hint">—</span>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
