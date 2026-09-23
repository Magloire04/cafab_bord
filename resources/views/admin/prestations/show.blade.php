<x-app-layout>
    <x-slot name="header">
        <h1>{{ $prestation->titre }}</h1>
    </x-slot>

    <p>{{ $prestation->lieu }} — {{ $prestation->date->format('d/m/Y') }} — statut : {{ $prestation->statut->value }}</p>

    <table>
        <thead>
            <tr>
                <th>Fille</th>
                <th>Montant</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($prestation->cachets as $cachet)
                <tr>
                    <td>{{ $cachet->fille->prenom }} {{ $cachet->fille->nom }}</td>
                    <td>{{ $cachet->montant }}</td>
                    <td>{{ $cachet->statut->value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-app-layout>
