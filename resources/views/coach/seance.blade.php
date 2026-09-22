<x-app-layout>
    <x-slot name="header">
        <h1>Ma répétition</h1>
    </x-slot>

    @if (! $seance)
        <p>Aucune séance à venir ou en cours ne vous est actuellement assignée.</p>
    @else
        <p>
            {{ $seance->date->format('d/m/Y') }} — début prévu {{ \Illuminate\Support\Carbon::parse($seance->heure_prevue)->format('H:i') }}
            — statut : {{ $seance->statut->value }}
        </p>

        @if ($seance->estEnCours())
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Arrivée</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filles as $fille)
                        @php
                            $pointage = $pointages->first(fn ($p) => $p->pointable_type === \App\Models\Fille::class && $p->pointable_id === $fille->id);
                        @endphp
                        <tr>
                            <td>{{ $fille->prenom }} {{ $fille->nom }}</td>
                            <td>{{ $pointage?->pointe_a?->format('H:i') ?? '—' }}</td>
                            <td>{{ $pointage?->statut_ponctualite?->value ?? 'Pas encore pointée' }}</td>
                            <td>
                                @unless ($pointage)
                                    <form action="{{ route('coach.seance.marquer-presente') }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="seance_id" value="{{ $seance->id }}">
                                        <input type="hidden" name="pointable_type" value="{{ \App\Models\Fille::class }}">
                                        <input type="hidden" name="pointable_id" value="{{ $fille->id }}">
                                        <input type="time" name="heure">
                                        <button type="submit">Marquer présente</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <form action="{{ route('coach.seance.cloturer') }}" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="seance_id" value="{{ $seance->id }}">
                <button type="submit">Clôturer la séance</button>
            </form>
        @endif
    @endif
</x-app-layout>
