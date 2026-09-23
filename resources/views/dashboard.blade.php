<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Tableau de bord</h2>
        <p class="text-muted mb-0">Bienvenue, {{ auth()->user()->name }}.</p>
    </x-slot>

    @if (auth()->user()->role === \App\Enums\UserRole::Admin)
        <div class="row g-4 mb-4" data-aos="fade-up">
            <div class="col-md-3">
                <div class="card p-4 shadow-sm border-0 bg-primary text-white h-100 position-relative overflow-hidden">
                    <h6 class="text-uppercase small mb-2 opacity-75 fw-bold">Coachs actifs</h6>
                    <h2 class="mb-0 fw-bold">{{ $stats['coachsActifs'] }}</h2>
                    <i class="fas fa-user-tie stat-card-icon"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 shadow-sm border-0 bg-success text-white h-100 position-relative overflow-hidden">
                    <h6 class="text-uppercase small mb-2 opacity-75 fw-bold">Filles actives</h6>
                    <h2 class="mb-0 fw-bold">{{ $stats['fillesActives'] }}</h2>
                    <i class="fas fa-users stat-card-icon"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 shadow-sm border-0 bg-info text-white h-100 position-relative overflow-hidden">
                    <h6 class="text-uppercase small mb-2 opacity-75 fw-bold">Séances aujourd'hui</h6>
                    <h2 class="mb-0 fw-bold">{{ $stats['seancesAujourdhui'] }}</h2>
                    <i class="fas fa-calendar-day stat-card-icon"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 shadow-sm border-0 bg-warning text-dark h-100 position-relative overflow-hidden">
                    <h6 class="text-uppercase small mb-2 opacity-75 fw-bold">Cachets dus</h6>
                    <h2 class="mb-0 fw-bold">{{ number_format($stats['cachetsDusMontant'], 0, ',', ' ') }} FCFA</h2>
                    <i class="fas fa-sack-dollar stat-card-icon"></i>
                </div>
            </div>
        </div>

        <div class="row g-4" data-aos="fade-up" data-aos-delay="100">
            <div class="col-md-4">
                <a href="{{ route('admin.pointages.index') }}" class="text-decoration-none text-dark">
                    <div class="card p-4 shadow-sm h-100">
                        <h5 class="fw-bold"><i class="fas fa-clipboard-check text-primary me-2"></i>Pointages</h5>
                        <p class="text-muted mb-0">{{ $stats['seancesEnCours'] }} séance(s) en cours actuellement.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.paiements.index') }}" class="text-decoration-none text-dark">
                    <div class="card p-4 shadow-sm h-100">
                        <h5 class="fw-bold"><i class="fas fa-money-check-dollar text-primary me-2"></i>Paiements</h5>
                        <p class="text-muted mb-0">{{ $stats['cachetsAValider'] }} cachet(s) déclaré(s) à valider.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.rapports.index') }}" class="text-decoration-none text-dark">
                    <div class="card p-4 shadow-sm h-100">
                        <h5 class="fw-bold"><i class="fas fa-chart-column text-primary me-2"></i>Rapports</h5>
                        <p class="text-muted mb-0">Ponctualité et dépenses de prestations, exportables en Excel.</p>
                    </div>
                </a>
            </div>
        </div>
    @else
        <div class="row g-4" data-aos="fade-up">
            <div class="col-md-6">
                <div class="card p-4 shadow-sm h-100">
                    <h5 class="fw-bold mb-3"><i class="fas fa-calendar-day text-primary me-2"></i>Répétition d'aujourd'hui</h5>
                    @if ($seanceDuJour)
                        <p class="mb-1">Prévue à <strong>{{ \Illuminate\Support\Carbon::parse($seanceDuJour->heure_prevue)->format('H:i') }}</strong></p>
                        <p class="text-muted mb-3">Statut : {{ $seanceDuJour->statut->value }}</p>
                        <a href="{{ route('coach.seance') }}" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-2"></i>Accéder à ma répétition
                        </a>
                    @else
                        <p class="text-muted mb-0">Aucune répétition planifiée aujourd'hui.</p>
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <div class="card p-4 shadow-sm h-100">
                    <h5 class="fw-bold mb-3"><i class="fas fa-clock-rotate-left text-primary me-2"></i>Historique</h5>
                    <p class="text-muted mb-3">Consulte tes séances passées et ta ponctualité.</p>
                    <a href="{{ route('coach.historique') }}" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-right me-2"></i>Voir mon historique
                    </a>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
