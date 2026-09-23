<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Rapport des dépenses de prestations</h1>
            <p class="field-hint mb-0">Cachets validés payés, par prestation et par fille.</p>
        </div>
    </x-slot>

    <div class="content-card mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="date_debut" class="field-label">Du</label>
                <input id="date_debut" name="date_debut" type="date" class="field-control" value="{{ request('date_debut') }}">
            </div>
            <div class="col-md-3">
                <label for="date_fin" class="field-label">Au</label>
                <input id="date_fin" name="date_fin" type="date" class="field-control" value="{{ request('date_fin') }}">
            </div>
            <div class="col-md-4">
                <label for="fille_id" class="field-label">Fille</label>
                <select id="fille_id" name="fille_id" class="field-select">
                    <option value="">Toutes</option>
                    @foreach ($filles as $fille)
                        <option value="{{ $fille->id }}" {{ (string) request('fille_id') === (string) $fille->id ? 'selected' : '' }}>
                            {{ $fille->prenom }} {{ $fille->nom }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn-ink w-100 justify-content-center">Filtrer</button>
            </div>
        </form>
        <div class="mt-3">
            <a href="{{ route('admin.rapports.depenses.excel', request()->query()) }}" target="_blank" class="btn-outline btn-sm">
                Exporter en Excel
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="kpi kpi-ink">
                <div class="label">Total</div>
                <div class="value">{{ number_format($total, 2, ',', ' ') }} FCFA</div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <table>
            <thead>
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
                        <td class="text-end fw-bold text-amount-ok">{{ number_format((float) $cachet->montant, 2, ',', ' ') }}</td>
                        <td>{{ $cachet->caisse_cafab_reference ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 field-hint">Aucun cachet trouvé avec ces filtres.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
