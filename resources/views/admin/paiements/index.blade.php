@php
    $totalDu = $prestations->sum('total_du');
    $totalPaye = $prestations->sum('total_paye');
    $totalReste = $prestations->sum('reste_a_payer');
    $tousLesCachets = $prestations->flatMap(fn ($ligne) => $ligne['prestation']->cachets->map(fn ($cachet) => [
        'prestation' => $ligne['prestation'],
        'cachet' => $cachet,
    ]));
    $declarationsATraiter = $tousLesCachets->filter(fn ($item) => in_array($item['cachet']->statut, [
        \App\Enums\StatutCachet::DeclareePayee,
        \App\Enums\StatutCachet::DeclareeNonPayee,
    ]))->count();
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Vue d'ensemble des paiements</h1>
            <p class="field-hint mb-0">Montants dus, validés et restant à payer par prestation.</p>
        </div>
    </x-slot>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="kpi">
                <div class="label">Total dû</div>
                <div class="value">{{ number_format($totalDu, 2, ',', ' ') }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi">
                <div class="label">Validé payé</div>
                <div class="value ok">{{ number_format($totalPaye, 2, ',', ' ') }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi">
                <div class="label">Reste à payer</div>
                <div class="value warn">{{ number_format($totalReste, 2, ',', ' ') }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi kpi-ink">
                <div class="label">Déclarations à traiter</div>
                <div class="value">{{ $declarationsATraiter }}</div>
            </div>
        </div>
    </div>

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
    </div>

    <div class="table-card mb-4">
        <table>
            <thead>
                <tr>
                    <th>Prestation</th>
                    <th>Date</th>
                    <th class="text-end">Montant total dû</th>
                    <th class="text-end">Montant validé payé</th>
                    <th class="text-end">Reste à payer</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prestations as $ligne)
                    <tr>
                        <td class="fw-bold">{{ $ligne['prestation']->titre }}</td>
                        <td>{{ $ligne['prestation']->date->format('d/m/Y') }}</td>
                        <td class="text-end">{{ number_format($ligne['total_du'], 2, ',', ' ') }}</td>
                        <td class="text-end fw-bold text-amount-ok">{{ number_format($ligne['total_paye'], 2, ',', ' ') }}</td>
                        <td class="text-end {{ (float) $ligne['reste_a_payer'] > 0 ? 'fw-bold text-amount-warn' : 'field-hint' }}">{{ number_format($ligne['reste_a_payer'], 2, ',', ' ') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 field-hint">Aucune prestation trouvée avec ces filtres.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Prestation</th>
                    <th>Fille</th>
                    <th>Montant</th>
                    <th>Déclaration</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tousLesCachets as $item)
                    <tr>
                        <td>{{ $item['prestation']->titre }}</td>
                        <td class="fw-bold">{{ $item['cachet']->fille->prenom }} {{ $item['cachet']->fille->nom }}</td>
                        <td>{{ number_format((float) $item['cachet']->montant, 2, ',', ' ') }}</td>
                        <td><x-badge-cachet :statut="$item['cachet']->statut" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 field-hint">Aucun cachet trouvé avec ces filtres.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
