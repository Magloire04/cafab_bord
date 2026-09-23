<x-app-layout>
    <x-slot name="header">
        <h1>Vue d'ensemble des paiements</h1>
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
    </form>

    <table>
        <thead>
            <tr>
                <th>Prestation</th>
                <th>Date</th>
                <th>Montant total dû</th>
                <th>Montant validé payé</th>
                <th>Reste à payer</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($prestations as $ligne)
                <tr>
                    <td>{{ $ligne['prestation']->titre }}</td>
                    <td>{{ $ligne['prestation']->date->format('d/m/Y') }}</td>
                    <td>{{ number_format($ligne['total_du'], 2, ',', ' ') }}</td>
                    <td>{{ number_format($ligne['total_paye'], 2, ',', ' ') }}</td>
                    <td>{{ number_format($ligne['reste_a_payer'], 2, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-app-layout>
