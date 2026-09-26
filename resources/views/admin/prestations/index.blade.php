<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Prestations</h1>
            <p class="field-hint mb-0">Liste des prestations et de leurs filles affectées.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.prestations.create') }}" class="btn-ink">Créer une prestation</a>
        </div>
    </x-slot>

    <x-onglets.prestations />

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Lieu</th>
                    <th>Date</th>
                    <th>Filles affectées</th>
                    <th>Statut</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prestations as $prestation)
                    <tr>
                        <td class="fw-bold">{{ $prestation->titre }}</td>
                        <td>{{ $prestation->lieu }}</td>
                        <td>{{ $prestation->date->format('d/m/Y') }}</td>
                        <td>{{ $prestation->cachets_count }}</td>
                        <td>
                            <span class="badge-st {{ $prestation->statut->value === 'active' ? 'st-heure' : 'st-absent' }}">
                                {{ $prestation->statut->value }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="{{ route('admin.prestations.show', $prestation) }}" class="btn-outline btn-sm">
                                    Voir
                                </a>
                                @if ($prestation->statut->value === 'active')
                                    <form action="{{ route('admin.prestations.annuler', $prestation) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn-danger-outline">
                                            Annuler
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 field-hint">Aucune prestation trouvée.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($prestations->hasPages())
        <div class="mt-3">
            {{ $prestations->links('pagination::bootstrap-5') }}
        </div>
    @endif
</x-app-layout>
