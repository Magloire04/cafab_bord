<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Rapport des dépenses de prestations</h2>
        <p class="text-muted mb-0">Cachets validés payés, par prestation et par fille.</p>
    </x-slot>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="date_debut" class="form-label small fw-bold">Du</label>
                    <input id="date_debut" name="date_debut" type="date" class="form-control" value="{{ request('date_debut') }}">
                </div>
                <div class="col-md-3">
                    <label for="date_fin" class="form-label small fw-bold">Au</label>
                    <input id="date_fin" name="date_fin" type="date" class="form-control" value="{{ request('date_fin') }}">
                </div>
                <div class="col-md-4">
                    <label for="fille_id" class="form-label small fw-bold">Fille</label>
                    <select id="fille_id" name="fille_id" class="form-select">
                        <option value="">Toutes</option>
                        @foreach ($filles as $fille)
                            <option value="{{ $fille->id }}" {{ (string) request('fille_id') === (string) $fille->id ? 'selected' : '' }}>
                                {{ $fille->prenom }} {{ $fille->nom }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Filtrer</button>
                </div>
            </form>
            <div class="mt-3">
                <a href="{{ route('admin.rapports.depenses.excel', request()->query()) }}" target="_blank" class="btn btn-outline-success btn-sm">
                    <i class="far fa-file-excel me-2"></i>Exporter en Excel
                </a>
            </div>
        </div>
    </div>

    <div class="card p-4 shadow-sm border-0 bg-primary text-white mb-4 position-relative overflow-hidden">
        <h6 class="text-uppercase small mb-2 opacity-75 fw-bold">Total</h6>
        <h2 class="mb-0 fw-bold">{{ number_format($total, 2, ',', ' ') }} FCFA</h2>
        <i class="fas fa-sack-dollar stat-card-icon"></i>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date de validation</th>
                            <th>Prestation</th>
                            <th>Date de la prestation</th>
                            <th>Fille</th>
                            <th class="text-end">Montant</th>
                            <th>Référence Caisse CAFAB</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cachets as $cachet)
                            <tr>
                                <td>{{ $cachet->validee_at?->format('d/m/Y') }}</td>
                                <td class="fw-bold">{{ $cachet->prestation->titre }}</td>
                                <td>{{ $cachet->prestation->date->format('d/m/Y') }}</td>
                                <td>{{ $cachet->fille->prenom }} {{ $cachet->fille->nom }}</td>
                                <td class="text-end text-success fw-bold">{{ number_format((float) $cachet->montant, 2, ',', ' ') }}</td>
                                <td>{{ $cachet->caisse_cafab_reference ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Aucun cachet trouvé avec ces filtres.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
