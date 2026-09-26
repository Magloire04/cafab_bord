<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-cafab.png') }}">

    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="logo-box">
                <img src="{{ asset('images/logo-cafab.png') }}" alt="CAFAB">
            </div>
            <x-horloge variante="sidebar" />

            <nav class="d-flex flex-column gap-1">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    Tableau de bord
                </a>

                @if (auth()->user()->role === \App\Enums\UserRole::Admin)
                    <a href="{{ route('admin.coaches.index') }}" class="nav-link {{ request()->routeIs(['admin.coaches.*', 'admin.filles.*']) ? 'active' : '' }}">
                        Registre
                    </a>
                    <a href="{{ route('admin.plannings.index') }}" class="nav-link {{ request()->routeIs(['admin.plannings.*', 'admin.calendrier', 'admin.pointages.*', 'seances.create-extraordinaire']) ? 'active' : '' }}">
                        Planning
                    </a>
                    <a href="{{ route('admin.prestations.index') }}" class="nav-link {{ request()->routeIs(['admin.prestations.*', 'admin.paiements.*']) ? 'active' : '' }}">
                        Prestations &amp; cachets
                    </a>
                    <a href="{{ route('admin.rapports.index') }}" class="nav-link {{ request()->routeIs('admin.rapports.*') ? 'active' : '' }}">
                        Rapports
                    </a>
                @endif

                @if (auth()->user()->role === \App\Enums\UserRole::Coach)
                    <a href="{{ route('coach.seance') }}" class="nav-link {{ request()->routeIs('coach.seance') ? 'active' : '' }}">
                        Ma répétition
                    </a>
                    <a href="{{ route('coach.historique') }}" class="nav-link {{ request()->routeIs('coach.historique') ? 'active' : '' }}">
                        Mon historique
                    </a>
                    <a href="{{ route('seances.create-extraordinaire') }}" class="nav-link {{ request()->routeIs('seances.create-extraordinaire') ? 'active' : '' }}">
                        Séance extraordinaire
                    </a>
                @endif

                <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}">
                    Mon profil
                </a>
            </nav>

            <div class="user">
                <div class="avatar">{{ auth()->user()->initials() }}</div>
                <div>
                    <div class="fw-semibold small user-name">{{ auth()->user()->name }}</div>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="border-0 bg-transparent p-0 small user-role-logout">
                            {{ auth()->user()->role === \App\Enums\UserRole::Admin ? 'Admin' : 'Coach' }} · Déconnexion
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <main class="main">
            <x-bandeau-seance />

            @if (session('message'))
                <div class="js-flash callout-success m-3" role="alert">
                    <span class="fw-semibold">{{ session('message') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="js-flash callout-danger m-3" role="alert">
                    <span class="fw-semibold">{{ session('error') }}</span>
                </div>
            @endif

            @isset($header)
                <div class="page-header">
                    {{ $header }}
                </div>
            @endisset

            <div class="p-4">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
