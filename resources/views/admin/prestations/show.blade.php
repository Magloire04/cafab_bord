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
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($prestation->cachets as $cachet)
                <tr>
                    <td>{{ $cachet->fille->prenom }} {{ $cachet->fille->nom }}</td>
                    <td>{{ $cachet->montant }}</td>
                    <td>{{ $cachet->statut->value }}</td>
                    <td>
                        @unless ($cachet->estFinalise())
                            <form action="{{ route('admin.cachets.ajuster-montant', $cachet) }}" method="POST" class="inline">
                                @csrf
                                @method('PATCH')
                                <input type="number" step="0.01" name="montant" value="{{ $cachet->montant }}">
                                <button type="submit">Ajuster</button>
                            </form>

                            <form action="{{ route('admin.cachets.valider', $cachet) }}" method="POST" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit">Valider le paiement</button>
                            </form>

                            <form action="{{ route('admin.cachets.corriger', $cachet) }}" method="POST" class="inline">
                                @csrf
                                @method('PATCH')
                                <select name="statut">
                                    <option value="declaree_payee">Corriger en : déclarée payée</option>
                                    <option value="declaree_non_payee">Corriger en : déclarée non payée</option>
                                </select>
                                <input type="text" name="motif" placeholder="Motif de la correction (obligatoire)">
                                <button type="submit">Corriger</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-app-layout>
