<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Tableau de bord</h1>
            <p class="field-hint mb-0">Bienvenue, {{ auth()->user()->name }}.</p>
        </div>
    </x-slot>

    @if (auth()->user()->role === \App\Enums\UserRole::Admin)
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="kpi">
                    <div class="label">Coachs actifs</div>
                    <div class="value">{{ $stats['coachsActifs'] }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi">
                    <div class="label">Filles actives</div>
                    <div class="value">{{ $stats['fillesActives'] }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi">
                    <div class="label">Séances aujourd'hui</div>
                    <div class="value">{{ $stats['seancesAujourdhui'] }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi kpi-ink">
                    <div class="label">Cachets dus</div>
                    <div class="value">{{ number_format($stats['cachetsDusMontant'], 0, ',', ' ') }} F</div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <a href="{{ route('admin.pointages.index') }}" class="link-tile">
                    <p class="link-tile-title">Pointages</p>
                    <p class="link-tile-desc">{{ $stats['seancesEnCours'] }} séance(s) en cours actuellement.</p>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.paiements.index') }}" class="link-tile">
                    <p class="link-tile-title">Paiements</p>
                    <p class="link-tile-desc">{{ $stats['cachetsAValider'] }} cachet(s) déclaré(s) à valider.</p>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.rapports.index') }}" class="link-tile">
                    <p class="link-tile-title">Rapports</p>
                    <p class="link-tile-desc">Ponctualité et dépenses de prestations, exportables en Excel.</p>
                </a>
            </div>
        </div>
    @else
        <div class="row g-3">
            <div class="col-md-6">
                <div class="content-card h-100">
                    <p class="section-title mb-2">Répétition d'aujourd'hui</p>
                    @if ($seanceDuJour)
                        <p class="mb-1">Prévue à <strong class="time">{{ \Illuminate\Support\Carbon::parse($seanceDuJour->heure_prevue)->format('H:i') }}</strong></p>
                        <p class="field-hint mb-3">Statut : {{ $seanceDuJour->statut->value }}</p>
                        <a href="{{ route('coach.seance') }}" class="btn-ink">Accéder à ma répétition</a>
                    @else
                        <p class="field-hint mb-0">Aucune répétition planifiée aujourd'hui.</p>
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-card h-100">
                    <p class="section-title mb-2">Historique</p>
                    <p class="field-hint mb-3">Consulte tes séances passées et ta ponctualité.</p>
                    <a href="{{ route('coach.historique') }}" class="btn-outline">Voir mon historique</a>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
