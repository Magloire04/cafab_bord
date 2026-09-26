@php
    $debutGrille = $mois->copy()->startOfMonth()->startOfWeek();
    $finGrille = $mois->copy()->endOfMonth()->endOfWeek();
    $parJour = $seances->groupBy(fn ($s) => $s->date->format('Y-m-d'));
    $jours = [];
    for ($d = $debutGrille->copy(); $d->lte($finGrille); $d->addDay()) {
        $jours[] = $d->copy();
    }
    $moisPrecedent = $mois->copy()->subMonth()->format('Y-m');
    $moisSuivant = $mois->copy()->addMonth()->format('Y-m');
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ ucfirst($mois->translatedFormat('F Y')) }}</h1>
            <p class="field-hint mb-0">Créneaux récurrents du planning actif · calendrier des répétitions</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.calendrier', ['mois' => $moisPrecedent]) }}" class="btn-outline">&larr;</a>
            <a href="{{ route('admin.calendrier', ['mois' => $moisSuivant]) }}" class="btn-outline">&rarr;</a>
            <a href="{{ route('seances.create-extraordinaire') }}" class="btn-ink">Séance extraordinaire</a>
        </div>
    </x-slot>

    <x-onglets.planning />

    <div class="content-card mb-3">
        <div class="calendar-legend">
            <span><span class="dot is-ok"></span>Présence ≥ 85 %</span>
            <span><span class="dot is-warn"></span>Présence 60 – 84 %</span>
            <span><span class="dot is-danger"></span>Présence &lt; 60 %</span>
            <span><span class="dot is-upcoming"></span>À venir</span>
        </div>
    </div>

    <div class="calendar-head">
        <div>Lun</div>
        <div>Mar</div>
        <div>Mer</div>
        <div>Jeu</div>
        <div>Ven</div>
        <div>Sam</div>
        <div>Dim</div>
    </div>

    <div class="calendar-grid">
        @foreach ($jours as $jour)
            @php
                $seancesDuJour = $parJour->get($jour->format('Y-m-d'), collect());
                $horsMois = ! $jour->isSameMonth($mois);
            @endphp
            <div class="calendar-cell {{ $horsMois ? 'is-outside' : '' }} {{ $jour->isToday() ? 'is-today' : '' }}">
                <div class="cell-day">{{ $jour->day }}{{ $jour->day === 1 ? ' '.$jour->translatedFormat('M') : '' }}{{ $jour->isToday() ? ' · aujourd\'hui' : '' }}</div>

                @foreach ($seancesDuJour as $seance)
                    @php
                        $heure = \Illuminate\Support\Carbon::parse($seance->heure_prevue)->format('H:i');
                    @endphp
                    @if ($seance->statut->value === 'en_cours')
                        <div class="calendar-pill is-current">{{ $heure }} · en cours</div>
                    @elseif ($seance->statut->value === 'a_venir')
                        <div class="calendar-pill is-upcoming">{{ $heure }} · à venir</div>
                    @elseif ($seance->taux_presence === null)
                        <div class="calendar-pill is-upcoming">{{ $heure }} · clôturée</div>
                    @else
                        @php
                            $niveau = $seance->taux_presence >= 85 ? 'is-ok' : ($seance->taux_presence >= 60 ? 'is-warn' : 'is-danger');
                        @endphp
                        @php $palier = (int) (round($seance->taux_presence / 5) * 5); @endphp
                        <div class="calendar-pill {{ $niveau }}">
                            {{ $heure }} · {{ $seance->taux_presence }} %
                            <div class="calendar-bar"><span class="w-pct-{{ $palier }}"></span></div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endforeach
    </div>
</x-app-layout>
