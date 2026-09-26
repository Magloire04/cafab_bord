@php
    $formaterMontant = fn ($montant) => number_format((float) $montant, 0, ',', ' ').' F';
    $cachetsOuverts = $prestation->cachets->reject->estFinalise();
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ $prestation->titre }}</h1>
            <p class="field-hint mb-0">
                {{ $prestation->lieu }} · {{ $prestation->date->translatedFormat('l j F Y') }} ·
                <span class="badge-st {{ $prestation->statut->value === 'active' ? 'st-heure' : 'st-absent' }}">
                    {{ $prestation->statut->value === 'active' ? 'Active' : 'Annulée' }}
                </span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.prestations.index') }}" class="btn-outline">Retour aux prestations</a>
        </div>
    </x-slot>

    <x-onglets.prestations />

    @if ($errors->any())
        <div class="callout-danger mb-4">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="kpi">
                <div class="label">Total dû</div>
                <div class="value">{{ $formaterMontant($indicateurs['total_du']) }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi">
                <div class="label">Validé payé</div>
                <div class="value ok">{{ $formaterMontant($indicateurs['valide']) }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi">
                <div class="label">Reste à payer</div>
                <div class="value warn">{{ $formaterMontant($indicateurs['reste']) }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi kpi-ink">
                <div class="label">Déclarations à traiter</div>
                <div class="value">{{ $indicateurs['a_traiter'] }}</div>
            </div>
        </div>
    </div>

    <p class="section-title mb-2">Détail par fille · cachet par défaut {{ $formaterMontant($prestation->montant_defaut) }}</p>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Fille</th>
                    <th class="text-end">Montant</th>
                    <th>Déclaration</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prestation->cachets as $cachet)
                    <tr @class(['pending' => in_array($cachet->statut->value, ['declaree_payee', 'declaree_non_payee'], true)])>
                        <td class="fw-bold">{{ $cachet->fille->prenom }} {{ $cachet->fille->nom }}</td>
                        <td class="text-end time">{{ $formaterMontant($cachet->montant) }}</td>
                        <td>
                            <x-badge-cachet :statut="$cachet->statut" :detail="$cachet->declaree_at?->translatedFormat('j M')" />
                        </td>
                        <td class="text-end">
                            @unless ($cachet->estFinalise())
                                <div class="d-inline-flex gap-2 align-items-center">
                                    <form action="{{ route('admin.cachets.valider', $cachet) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn-success">Valider</button>
                                    </form>
                                    <x-menu-actions>
                                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modale-ajuster-{{ $cachet->id }}">Ajuster le montant</button></li>
                                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modale-corriger-{{ $cachet->id }}">Corriger la déclaration</button></li>
                                    </x-menu-actions>
                                </div>
                            @endunless

                            @if ($cachet->statut->value === 'validee_payee')
                                @if ($cachet->depense_creee_at)
                                    <span class="field-hint text-amount-ok">Dépense créée dans Caisse CAFAB</span>
                                @else
                                    <div class="d-inline-flex gap-2 align-items-center">
                                        <span class="field-error mb-0">Dépense non créée : {{ $cachet->depense_erreur }}</span>
                                        <form action="{{ route('admin.cachets.reessayer-depense', $cachet) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn-ink btn-sm">Réessayer</button>
                                        </form>
                                    </div>
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

    @foreach ($cachetsOuverts as $cachet)
        <div class="modal fade" id="modale-ajuster-{{ $cachet->id }}" tabindex="-1" aria-labelledby="titre-ajuster-{{ $cachet->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content modale-cafab" action="{{ route('admin.cachets.ajuster-montant', $cachet) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="modal-body">
                        <h2 class="section-title mb-3" id="titre-ajuster-{{ $cachet->id }}">Ajuster le montant · {{ $cachet->fille->prenom }} {{ $cachet->fille->nom }}</h2>
                        <label for="montant-{{ $cachet->id }}" class="field-label">Montant (F)</label>
                        <input id="montant-{{ $cachet->id }}" type="number" step="0.01" min="0" name="montant" value="{{ $cachet->montant }}" class="field-control" required>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn-outline btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn-ink btn-sm">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modale-corriger-{{ $cachet->id }}" tabindex="-1" aria-labelledby="titre-corriger-{{ $cachet->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content modale-cafab" action="{{ route('admin.cachets.corriger', $cachet) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="modal-body">
                        <h2 class="section-title mb-3" id="titre-corriger-{{ $cachet->id }}">Corriger la déclaration · {{ $cachet->fille->prenom }} {{ $cachet->fille->nom }}</h2>
                        <label for="statut-{{ $cachet->id }}" class="field-label">Nouvelle déclaration</label>
                        <select id="statut-{{ $cachet->id }}" name="statut" class="field-select mb-3">
                            <option value="declaree_payee">Déclarée payée</option>
                            <option value="declaree_non_payee">Déclarée non payée</option>
                        </select>
                        <label for="motif-{{ $cachet->id }}" class="field-label">Motif (obligatoire)</label>
                        <textarea id="motif-{{ $cachet->id }}" name="motif" class="field-control" rows="3" required minlength="5"></textarea>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn-outline btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn-corrige">Corriger</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
</x-app-layout>
