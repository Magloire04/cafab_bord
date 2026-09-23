@php
    $statutSeanceClass = $seance ? match ($seance->statut->value) {
        'en_cours' => 'st-retard',
        'cloturee' => 'st-absent',
        default => 'st-heure',
    } : null;
    $statutSeanceLabel = $seance ? match ($seance->statut->value) {
        'en_cours' => 'En cours',
        'cloturee' => 'Clôturée',
        default => 'À venir',
    } : null;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Ma répétition</h1>
            @if ($seance)
                <p class="field-hint mb-0">
                    {{ $seance->date->format('d/m/Y') }} — début prévu {{ \Illuminate\Support\Carbon::parse($seance->heure_prevue)->format('H:i') }}
                    — statut :
                    <span class="badge-st {{ $statutSeanceClass }}">{{ $statutSeanceLabel }}</span>
                </p>
            @endif
        </div>
        @if ($seance && $seance->estEnCours())
            <div class="d-flex gap-2">
                <form action="{{ route('coach.seance.cloturer') }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="seance_id" value="{{ $seance->id }}">
                    <button type="submit" class="btn-ink">
                        Clôturer la séance
                    </button>
                </form>
            </div>
        @endif
    </x-slot>

    @if (! $seance)
        <div class="content-card">
            <p class="field-hint mb-0">Aucune séance à venir ou en cours ne vous est actuellement assignée.</p>
        </div>
    @else
        @if ($seance->estEnCours())
            @php
                $pointagesFilles = $pointages->where('pointable_type', \App\Models\Fille::class);
                $aLHeureCount = $pointagesFilles->filter(fn ($p) => $p->statut_ponctualite?->value === 'a_l_heure')->count();
                $enRetardCount = $pointagesFilles->count() - $aLHeureCount;
                $pasPointeesCount = $filles->count() - $pointagesFilles->count();
            @endphp

            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="kpi">
                        <div class="label">Pointées</div>
                        <div class="value">{{ $pointagesFilles->count() }} / {{ $filles->count() }}</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi">
                        <div class="label">À l'heure</div>
                        <div class="value ok">{{ $aLHeureCount }}</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi">
                        <div class="label">En retard</div>
                        <div class="value warn">{{ $enRetardCount }}</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi">
                        <div class="label">Pas encore pointées</div>
                        <div class="value">{{ $pasPointeesCount }}</div>
                    </div>
                </div>
            </div>

            <div class="table-card mb-4">
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
                                $minutesRetard = $pointage && in_array($pointage->statut_ponctualite?->value, ['en_retard', 'retard_fort'])
                                    ? $pointage->minutes_retard
                                    : null;
                            @endphp
                            <tr>
                                <td class="fw-bold">{{ $fille->prenom }} {{ $fille->nom }}</td>
                                <td class="time">{{ $pointage?->pointe_a?->format('H:i') ?? '—' }}</td>
                                <td>
                                    <x-badge-ponctualite :statut="$pointage?->statut_ponctualite" :minutes="$minutesRetard" />
                                </td>
                                <td>
                                    @unless ($pointage)
                                        <form action="{{ route('coach.seance.marquer-presente') }}" method="POST" class="d-flex gap-2 align-items-center">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="seance_id" value="{{ $seance->id }}">
                                            <input type="hidden" name="pointable_type" value="{{ \App\Models\Fille::class }}">
                                            <input type="hidden" name="pointable_id" value="{{ $fille->id }}">
                                            <input type="time" name="heure" class="field-control w-auto">
                                            <button type="submit" class="btn-ink btn-sm text-nowrap">
                                                Marquer présente
                                            </button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</x-app-layout>
