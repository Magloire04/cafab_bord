<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Rapport de ponctualité</h2>
        <p class="text-muted mb-0">Présences, retards et absences par coach et par fille.</p>
    </x-slot>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="date_debut" class="form-label small fw-bold">Du</label>
                    <input id="date_debut" name="date_debut" type="date" class="form-control" value="{{ request('date_debut') }}">
                </div>
                <div class="col-md-2">
                    <label for="date_fin" class="form-label small fw-bold">Au</label>
                    <input id="date_fin" name="date_fin" type="date" class="form-control" value="{{ request('date_fin') }}">
                </div>
                <div class="col-md-3">
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
                <div class="col-md-3">
                    <label for="coach_id" class="form-label small fw-bold">Coach</label>
                    <select id="coach_id" name="coach_id" class="form-select">
                        <option value="">Tous</option>
                        @foreach ($coaches as $coach)
                            <option value="{{ $coach->id }}" {{ (string) request('coach_id') === (string) $coach->id ? 'selected' : '' }}>
                                {{ $coach->user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-filter me-2"></i>Filtrer</button>
                </div>
            </form>
            <div class="mt-3">
                <a href="{{ route('admin.rapports.ponctualite.excel', request()->query()) }}" target="_blank" class="btn btn-outline-success btn-sm">
                    <i class="far fa-file-excel me-2"></i>Exporter en Excel
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Personne</th>
                            <th>Type</th>
                            <th class="text-end">Présences</th>
                            <th class="text-end">Retards</th>
                            <th class="text-end">Retard cumulé (min)</th>
                            <th class="text-end">Absences</th>
                            <th class="text-end">Taux de présence</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            <tr>
                                <td class="fw-bold">{{ $ligne['nom'] }}</td>
                                <td>{{ $ligne['type'] }}</td>
                                <td class="text-end">{{ $ligne['presences'] }}</td>
                                <td class="text-end">{{ $ligne['retards'] }}</td>
                                <td class="text-end">{{ $ligne['retard_cumule'] }}</td>
                                <td class="text-end">{{ $ligne['absences'] }}</td>
                                <td class="text-end">
                                    <span class="badge {{ $ligne['taux_presence'] >= 80 ? 'bg-success' : ($ligne['taux_presence'] >= 50 ? 'bg-warning text-dark' : 'bg-danger') }}">
                                        {{ $ligne['taux_presence'] }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Aucune donnée trouvée avec ces filtres.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
