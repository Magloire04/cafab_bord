<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ $prestation->titre }}</h1>
            <p class="field-hint mb-0">
                {{ $prestation->lieu }} — {{ $prestation->date->format('d/m/Y') }} — statut :
                <span class="badge-st {{ $prestation->statut->value === 'active' ? 'st-heure' : 'st-absent' }}">{{ $prestation->statut->value }}</span>
            </p>
        </div>
    </x-slot>

    @if ($errors->any())
        <div class="callout-danger mb-4">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Fille</th>
                    <th>Montant</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prestation->cachets as $cachet)
                    <tr>
                        <td class="fw-bold">{{ $cachet->fille->prenom }} {{ $cachet->fille->nom }}</td>
                        <td>{{ $cachet->montant }}</td>
                        <td>
                            <x-badge-cachet :statut="$cachet->statut" />
                        </td>
                        <td>
                            @unless ($cachet->estFinalise())
                                <div class="d-flex flex-column gap-2">
                                    <form action="{{ route('admin.cachets.ajuster-montant', $cachet) }}" method="POST" class="d-flex gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="number" step="0.01" name="montant" value="{{ $cachet->montant }}" size="8" class="field-control w-auto">
                                        <button type="submit" class="btn-outline btn-sm text-nowrap">
                                            Ajuster
                                        </button>
                                    </form>

                                    <form action="{{ route('admin.cachets.valider', $cachet) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn-success">
                                            Valider le paiement
                                        </button>
                                    </form>

                                    <form action="{{ route('admin.cachets.corriger', $cachet) }}" method="POST" class="d-flex flex-wrap gap-2 align-items-center">
                                        @csrf
                                        @method('PATCH')
                                        <select name="statut" class="field-select w-auto">
                                            <option value="declaree_payee">Corriger en : déclarée payée</option>
                                            <option value="declaree_non_payee">Corriger en : déclarée non payée</option>
                                        </select>
                                        <input type="text" name="motif" placeholder="Motif de la correction (obligatoire)" required minlength="5" class="field-control w-auto">
                                        <button type="submit" class="btn-corrige">
                                            Corriger
                                        </button>
                                    </form>
                                </div>
                            @endunless

                            @if ($cachet->statut->value === 'validee_payee')
                                @if ($cachet->depense_creee_at)
                                    <p class="field-hint mb-0 mt-2">Dépense créée dans Caisse CAFAB.</p>
                                @else
                                    <p class="field-error mb-2 mt-2">Dépense non créée : {{ $cachet->depense_erreur }}</p>
                                    <form action="{{ route('admin.cachets.reessayer-depense', $cachet) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn-outline btn-sm">
                                            Réessayer
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 field-hint">Aucun cachet pour cette prestation.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
