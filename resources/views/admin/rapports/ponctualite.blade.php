<x-app-layout>
    <x-slot name="header">
        <h1>Rapport de ponctualité</h1>
    </x-slot>

    <form method="GET">
        <label for="date_debut">Du</label>
        <input id="date_debut" name="date_debut" type="date" value="{{ request('date_debut') }}">

        <label for="date_fin">Au</label>
        <input id="date_fin" name="date_fin" type="date" value="{{ request('date_fin') }}">

        <label for="fille_id">Fille</label>
        <select id="fille_id" name="fille_id">
            <option value="">Toutes</option>
            @foreach ($filles as $fille)
                <option value="{{ $fille->id }}" {{ (string) request('fille_id') === (string) $fille->id ? 'selected' : '' }}>
                    {{ $fille->prenom }} {{ $fille->nom }}
                </option>
            @endforeach
        </select>

        <label for="coach_id">Coach</label>
        <select id="coach_id" name="coach_id">
            <option value="">Tous</option>
            @foreach ($coaches as $coach)
                <option value="{{ $coach->id }}" {{ (string) request('coach_id') === (string) $coach->id ? 'selected' : '' }}>
                    {{ $coach->user->name }}
                </option>
            @endforeach
        </select>

        <button type="submit">Filtrer</button>
        <a href="{{ route('admin.rapports.ponctualite.excel', request()->query()) }}" target="_blank">Exporter en Excel</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>Personne</th>
                <th>Type</th>
                <th>Présences</th>
                <th>Retards</th>
                <th>Retard cumulé (min)</th>
                <th>Absences</th>
                <th>Taux de présence</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lignes as $ligne)
                <tr>
                    <td>{{ $ligne['nom'] }}</td>
                    <td>{{ $ligne['type'] }}</td>
                    <td>{{ $ligne['presences'] }}</td>
                    <td>{{ $ligne['retards'] }}</td>
                    <td>{{ $ligne['retard_cumule'] }}</td>
                    <td>{{ $ligne['absences'] }}</td>
                    <td>{{ $ligne['taux_presence'] }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-app-layout>
