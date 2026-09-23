<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Ma répétition</h2>
    </x-slot>

    @if (! $seance)
        <div class="card p-4 shadow-sm">
            <p class="text-muted mb-0"><i class="fas fa-circle-info me-2"></i>Aucune séance à venir ou en cours ne vous est actuellement assignée.</p>
        </div>
    @else
        <div class="card p-4 shadow-sm mb-4">
            <p class="mb-0">
                <i class="fas fa-calendar-day text-primary me-2"></i>
                {{ $seance->date->format('d/m/Y') }} — début prévu {{ \Illuminate\Support\Carbon::parse($seance->heure_prevue)->format('H:i') }}
                — statut :
                <span class="badge {{ $seance->statut->value === 'en_cours' ? 'bg-info text-dark' : ($seance->statut->value === 'cloturee' ? 'bg-secondary' : 'bg-warning text-dark') }}">
                    {{ $seance->statut->value }}
                </span>
            </p>
        </div>

        @if ($seance->estEnCours())
            <div class="card shadow-sm mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
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
                                        <td class="fw-bold">{{ $fille->prenom }} {{ $fille->nom }}</td>
                                        <td>{{ $pointage?->pointe_a?->format('H:i') ?? '—' }}</td>
                                        <td>
                                            @if ($pointage)
                                                @php
                                                    $ponctualiteBadgeClass = match ($pointage->statut_ponctualite?->value) {
                                                        'a_l_heure' => 'bg-success',
                                                        'en_retard' => 'bg-warning text-dark',
                                                        'retard_fort' => 'bg-danger',
                                                        'absent' => 'bg-secondary',
                                                        default => 'bg-secondary',
                                                    };
                                                @endphp
                                                <span class="badge {{ $ponctualiteBadgeClass }}">{{ $pointage->statut_ponctualite?->value }}</span>
                                            @else
                                                <span class="badge bg-light text-dark">Pas encore pointée</span>
                                            @endif
                                        </td>
                                        <td>
                                            @unless ($pointage)
                                                <form action="{{ route('coach.seance.marquer-presente') }}" method="POST" class="d-flex gap-2 align-items-center">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="seance_id" value="{{ $seance->id }}">
                                                    <input type="hidden" name="pointable_type" value="{{ \App\Models\Fille::class }}">
                                                    <input type="hidden" name="pointable_id" value="{{ $fille->id }}">
                                                    <input type="time" name="heure" class="form-control form-control-sm w-auto">
                                                    <button type="submit" class="btn btn-primary btn-sm text-nowrap">
                                                        <i class="fas fa-check me-1"></i>Marquer présente
                                                    </button>
                                                </form>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <form action="{{ route('coach.seance.cloturer') }}" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="seance_id" value="{{ $seance->id }}">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-flag-checkered me-2"></i>Clôturer la séance
                </button>
            </form>
        @endif
    @endif
</x-app-layout>
