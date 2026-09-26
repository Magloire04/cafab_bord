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

    @if ($identifiants = session('identifiants'))
        <div class="callout-info mb-4">
            <div>
                <p class="fw-bold mb-1">Identifiants à transmettre à {{ $identifiants['nom'] }}</p>
                <p class="mb-1">Email : <span class="mono">{{ $identifiants['email'] }}</span> · Mot de passe provisoire : <span class="mono">{{ $identifiants['mot_de_passe'] }}</span></p>
                <p class="field-hint mb-0">Ce mot de passe ne sera plus affiché. Le coach devra le changer à sa première connexion.</p>
            </div>
            <button type="button" class="btn-outline btn-sm" data-copier="Email : {{ $identifiants['email'] }} · Mot de passe : {{ $identifiants['mot_de_passe'] }}">Copier</button>
        </div>
    @endif

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Contact</th>
                    <th>PIN</th>
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
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
                        <td class="text-end">
                            <x-menu-actions>
                                <li><a class="dropdown-item" href="{{ route('admin.coaches.edit', $coach) }}">Modifier</a></li>
                                <li>
                                    <form action="{{ route('admin.coaches.toggle-statut', $coach) }}" method="POST"
                                          data-confirmer="{{ $coach->statut->value === 'actif' ? 'Désactiver' : 'Réactiver' }} le compte de {{ $coach->user->name }} ?">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="dropdown-item">{{ $coach->statut->value === 'actif' ? 'Désactiver' : 'Réactiver' }}</button>
                                    </form>
                                </li>
                                <li>
                                    <form action="{{ route('admin.coaches.regenerate-pin', $coach) }}" method="POST"
                                          data-confirmer="Générer un nouveau code PIN pour {{ $coach->user->name }} ? L'ancien ne fonctionnera plus au kiosque.">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="dropdown-item">Régénérer le PIN</button>
                                    </form>
                                </li>
                                <li>
                                    <form action="{{ route('admin.coaches.reset-password', $coach) }}" method="POST"
                                          data-confirmer="Réinitialiser le mot de passe de {{ $coach->user->name }} ? Ses sessions seront fermées et il devra choisir un nouveau mot de passe.">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="dropdown-item">Réinitialiser le mot de passe</button>
                                    </form>
                                </li>
                            </x-menu-actions>
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
