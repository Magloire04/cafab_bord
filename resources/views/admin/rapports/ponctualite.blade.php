<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Rapport de ponctualité</h1>
            <p class="field-hint mb-0">Présences, retards et absences par coach et par fille.</p>
        </div>
    </x-slot>

    <div class="content-card mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label for="date_debut" class="field-label">Du</label>
                <input id="date_debut" name="date_debut" type="date" class="field-control" value="{{ request('date_debut') }}">
            </div>
            <div class="col-md-2">
                <label for="date_fin" class="field-label">Au</label>
                <input id="date_fin" name="date_fin" type="date" class="field-control" value="{{ request('date_fin') }}">
            </div>
            <div class="col-md-3">
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
            <div class="col-md-3">
                <label for="coach_id" class="field-label">Coach</label>
                <select id="coach_id" name="coach_id" class="field-select">
                    <option value="">Tous</option>
                    @foreach ($coaches as $coach)
                        <option value="{{ $coach->id }}" {{ (string) request('coach_id') === (string) $coach->id ? 'selected' : '' }}>
                            {{ $coach->user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn-ink w-100 justify-content-center">Filtrer</button>
            </div>
        </form>
        <div class="mt-3">
            <a href="{{ route('admin.rapports.ponctualite.excel', request()->query()) }}" target="_blank" class="btn-outline btn-sm">
                Exporter en Excel
            </a>
        </div>
    </div>

    <div class="table-card">
        <table>
            <thead>
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
                            <span class="badge-st {{ $ligne['taux_presence'] >= 80 ? 'st-heure' : ($ligne['taux_presence'] >= 50 ? 'st-retard' : 'st-fort') }}">
                                {{ $ligne['taux_presence'] }}%
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 field-hint">Aucune donnée trouvée avec ces filtres.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
