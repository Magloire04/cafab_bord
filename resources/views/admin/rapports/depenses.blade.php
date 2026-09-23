<x-app-layout>
    <x-slot name="header">
        <h1>Rapport des dépenses de prestations</h1>
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

        <button type="submit">Filtrer</button>
        <a href="{{ route('admin.rapports.depenses.excel', request()->query()) }}" target="_blank">Exporter en Excel</a>
    </form>

    <p>Total : {{ number_format($total, 2, ',', '') }} FCFA</p>

    <table>
        <thead>
            <tr>
                <th>Date de validation</th>
                <th>Prestation</th>
                <th>Date de la prestation</th>
                <th>Fille</th>
                <th>Montant</th>
                <th>Référence Caisse CAFAB</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cachets as $cachet)
                <tr>
                    <td>{{ $cachet->validee_at?->format('d/m/Y') }}</td>
                    <td>{{ $cachet->prestation->titre }}</td>
                    <td>{{ $cachet->prestation->date->format('d/m/Y') }}</td>
                    <td>{{ $cachet->fille->prenom }} {{ $cachet->fille->nom }}</td>
                    <td>{{ number_format((float) $cachet->montant, 2, ',', '') }}</td>
                    <td>{{ $cachet->caisse_cafab_reference ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-app-layout>
