<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Vue d'ensemble des paiements</h2>
        <p class="text-muted mb-0">Montants dus, validés et restant à payer par prestation.</p>
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
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
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
                                <td class="text-end text-success fw-bold">{{ number_format($ligne['total_paye'], 2, ',', ' ') }}</td>
                                <td class="text-end {{ (float) $ligne['reste_a_payer'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">{{ number_format($ligne['reste_a_payer'], 2, ',', ' ') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Aucune prestation trouvée avec ces filtres.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
