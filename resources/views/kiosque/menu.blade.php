<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pointage — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-cafab.png') }}">
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="kiosk kiosk-dark d-flex flex-column">
        <div class="flex-grow-1">
            <p class="field-hint mb-1">Bonsoir</p>
            <h1 class="kiosk-hero mb-5">{{ $nom }}</h1>

            <div class="row g-4">
                <div class="col-md-6">
                    <form action="{{ route('kiosque.pointer') }}" method="POST">
                        @csrf
                        <button type="submit" class="kiosk-tile kiosk-tile-primary">
                            <span class="kiosk-tile-shape"></span>
                            <p class="kiosk-tile-title mb-0">Pointer ma présence</p>
                            <p class="kiosk-tile-desc">Enregistrer ton arrivée à la répétition.</p>
                        </button>
                    </form>
                </div>

                <div class="col-md-6">
                    @if ($cachetsEligibles)
                        <a href="{{ route('kiosque.cachets.index') }}" class="kiosk-tile kiosk-tile-secondary">
                            <span class="kiosk-pending-pill">{{ $cachetsEnAttente }} en attente</span>
                            <span class="kiosk-tile-shape"></span>
                            <p class="kiosk-tile-title mb-0">Déclarer un cachet</p>
                            <p class="kiosk-tile-desc">Dire si tu as reçu l'argent de tes prestations passées.</p>
                        </a>
                    @else
                        <div class="kiosk-tile kiosk-tile-secondary kiosk-tile-disabled">
                            <span class="kiosk-tile-shape"></span>
                            <p class="kiosk-tile-title mb-0">Déclarer un cachet</p>
                            <p class="kiosk-tile-desc">Aucun cachet à déclarer pour le moment.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="kiosk-bottombar">
            <a href="{{ route('kiosque.home') }}" class="field-hint text-decoration-none mb-0">
                Ce n'est pas toi ? Touche ici pour revenir en arrière.
            </a>
        </div>
    </div>
</body>
</html>
