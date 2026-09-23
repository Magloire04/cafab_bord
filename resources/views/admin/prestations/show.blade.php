<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">{{ $prestation->titre }}</h2>
        <p class="text-muted mb-0">
            {{ $prestation->lieu }} — {{ $prestation->date->format('d/m/Y') }} — statut :
            <span class="badge {{ $prestation->statut->value === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $prestation->statut->value }}</span>
        </p>
    </x-slot>

    @if ($errors->any())
        <div class="alert alert-danger shadow-sm border-0 mb-4">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
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
                                    @php
                                        $cachetBadgeClass = match ($cachet->statut->value) {
                                            'du' => 'bg-warning text-dark',
                                            'declaree_payee' => 'bg-info text-dark',
                                            'declaree_non_payee' => 'bg-danger',
                                            'validee_payee' => 'bg-success',
                                            'annule' => 'bg-secondary',
                                            default => 'bg-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $cachetBadgeClass }}">{{ $cachet->statut->value }}</span>
                                </td>
                                <td>
                                    @unless ($cachet->estFinalise())
                                        <div class="d-flex flex-column gap-2">
                                            <form action="{{ route('admin.cachets.ajuster-montant', $cachet) }}" method="POST" class="d-flex gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <input type="number" step="0.01" name="montant" value="{{ $cachet->montant }}" size="8" class="form-control form-control-sm w-auto">
                                                <button type="submit" class="btn btn-outline-secondary btn-sm text-nowrap">
                                                    <i class="fas fa-pen me-1"></i>Ajuster
                                                </button>
                                            </form>

                                            <form action="{{ route('admin.cachets.valider', $cachet) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check me-1"></i>Valider le paiement
                                                </button>
                                            </form>

                                            <form action="{{ route('admin.cachets.corriger', $cachet) }}" method="POST" class="d-flex flex-wrap gap-2 align-items-center">
                                                @csrf
                                                @method('PATCH')
                                                <select name="statut" class="form-select form-select-sm w-auto">
                                                    <option value="declaree_payee">Corriger en : déclarée payée</option>
                                                    <option value="declaree_non_payee">Corriger en : déclarée non payée</option>
                                                </select>
                                                <input type="text" name="motif" placeholder="Motif de la correction (obligatoire)" required minlength="5" class="form-control form-control-sm w-auto">
                                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-rotate me-1"></i>Corriger
                                                </button>
                                            </form>
                                        </div>
                                    @endunless

                                    @if ($cachet->statut->value === 'validee_payee')
                                        @if ($cachet->depense_creee_at)
                                            <p class="text-success small mb-0 mt-2"><i class="fas fa-check-circle me-1"></i>Dépense créée dans Caisse CAFAB.</p>
                                        @else
                                            <p class="text-danger small mb-2 mt-2"><i class="fas fa-triangle-exclamation me-1"></i>Dépense non créée : {{ $cachet->depense_erreur }}</p>
                                            <form action="{{ route('admin.cachets.reessayer-depense', $cachet) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-outline-warning btn-sm">
                                                    <i class="fas fa-rotate-right me-1"></i>Réessayer
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Aucun cachet pour cette prestation.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
