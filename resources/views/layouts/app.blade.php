<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <nav class="navbar navbar-expand-lg mb-4 sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="{{ route('dashboard') }}">
                <img src="{{ asset('images/logo-cafab.png') }}" alt="CAFAB">
            </a>
            <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fas fa-bars"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'fw-bold' : '' }}" href="{{ route('dashboard') }}">
                            <i class="fas fa-gauge-high me-1"></i>Dashboard
                        </a>
                    </li>

                    @if (auth()->user()->role === \App\Enums\UserRole::Admin)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.coaches.*') ? 'fw-bold' : '' }}" href="{{ route('admin.coaches.index') }}">
                                <i class="fas fa-user-tie me-1"></i>Coachs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.filles.*') ? 'fw-bold' : '' }}" href="{{ route('admin.filles.index') }}">
                                <i class="fas fa-users me-1"></i>Filles
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs(['admin.plannings.*', 'admin.calendrier', 'admin.pointages.*', 'seances.create-extraordinaire']) ? 'fw-bold' : '' }}" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-calendar-days me-1"></i>Répétitions
                            </a>
                            <ul class="dropdown-menu shadow border-0">
                                <li><a class="dropdown-item" href="{{ route('admin.plannings.index') }}"><i class="fas fa-list-check me-2 text-muted"></i>Planning récurrent</a></li>
                                <li><a class="dropdown-item" href="{{ route('admin.calendrier') }}"><i class="fas fa-calendar me-2 text-muted"></i>Calendrier</a></li>
                                <li><a class="dropdown-item" href="{{ route('admin.pointages.index') }}"><i class="fas fa-clipboard-check me-2 text-muted"></i>Pointages</a></li>
                                <li><a class="dropdown-item" href="{{ route('seances.create-extraordinaire') }}"><i class="fas fa-plus me-2 text-muted"></i>Séance extraordinaire</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs(['admin.prestations.*', 'admin.paiements.*']) ? 'fw-bold' : '' }}" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-money-check-dollar me-1"></i>Cachets
                            </a>
                            <ul class="dropdown-menu shadow border-0">
                                <li><a class="dropdown-item" href="{{ route('admin.prestations.index') }}"><i class="fas fa-masks-theater me-2 text-muted"></i>Prestations</a></li>
                                <li><a class="dropdown-item" href="{{ route('admin.paiements.index') }}"><i class="fas fa-sack-dollar me-2 text-muted"></i>Paiements</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.rapports.*') ? 'fw-bold' : '' }}" href="{{ route('admin.rapports.index') }}">
                                <i class="fas fa-chart-column me-1"></i>Rapports
                            </a>
                        </li>
                    @endif

                    @if (auth()->user()->role === \App\Enums\UserRole::Coach)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('coach.seance') ? 'fw-bold' : '' }}" href="{{ route('coach.seance') }}">
                                <i class="fas fa-clipboard-check me-1"></i>Ma répétition
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('coach.historique') ? 'fw-bold' : '' }}" href="{{ route('coach.historique') }}">
                                <i class="fas fa-clock-rotate-left me-1"></i>Mon historique
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('seances.create-extraordinaire') ? 'fw-bold' : '' }}" href="{{ route('seances.create-extraordinaire') }}">
                                <i class="fas fa-plus me-1"></i>Séance extraordinaire
                            </a>
                        </li>
                    @endif
                </ul>

                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item text-nowrap">
                        <span class="nav-link text-white fw-bold me-2 d-inline-flex align-items-center">
                            <i class="fas fa-user-circle me-1"></i>{{ auth()->user()->name }}
                            <span class="badge bg-light text-dark ms-2">{{ auth()->user()->role === \App\Enums\UserRole::Admin ? 'Admin' : 'Coach' }}</span>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('profile.edit') }}"><i class="fas fa-cog me-1"></i>Profil</a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="nav-link btn btn-danger btn-sm text-white px-3 rounded-8">
                                <i class="fas fa-sign-out-alt me-1"></i>Déconnexion
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        @if (session('message'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('message') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @isset($header)
            <div class="mb-4 above-navbar">
                {{ $header }}
            </div>
        @endisset

        {{ $slot }}
    </div>

    <footer class="mt-5 py-4 bg-white border-top">
        <div class="container text-center text-muted">
            <p class="mb-0">&copy; {{ now()->year }} Présence &amp; Paiements CAFAB. Tous droits réservés.</p>
        </div>
    </footer>
</body>
</html>
