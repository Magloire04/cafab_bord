<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pointage — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-cafab.png') }}">
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body x-data="{}" x-init="setTimeout(() => window.location = '{{ route('kiosque.home') }}', 4000)">
    <div class="kiosk-confirm-shell">
        <div class="kiosk-progress kiosk-progress-4s"></div>

        <div class="flex-grow-1 d-flex align-items-center justify-content-center">
            <div class="text-center kiosk-confirm-panel">
                @if (isset($erreur))
                    <div class="kiosk-confirm-icon is-error">!</div>
                    <h1 class="kiosk-title mb-2">{{ $nom }}, un instant.</h1>
                    <p class="field-hint">{{ $erreur }}</p>
                @else
                    <div class="kiosk-confirm-icon">✓</div>
                    <h1 class="kiosk-title mb-2">C'est noté, {{ $nom }}.</h1>

                    <div class="kiosk-confirm-card">
                        <div>
                            <p class="overline mb-1">Arrivée</p>
                            <p class="fw-bold time mb-0">{{ $pointage->pointe_a->format('H:i') }}</p>
                        </div>
                        <div>
                            <p class="overline mb-1">Statut</p>
                            <x-badge-ponctualite :statut="$pointage->statut_ponctualite" />
                        </div>
                    </div>

                    @if (($cachetsEnAttente ?? 0) > 0)
                        <div class="callout-info mt-4">
                            <span>Tu as <strong>{{ $cachetsEnAttente }}</strong> cachet(s) à déclarer.</span>
                            <a href="{{ route('kiosque.cachets.index') }}" class="btn-ink">Les voir maintenant</a>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <div class="kiosk-bottombar">
            <span class="field-hint mb-0">Passe la tablette à la suivante.</span>
            <span class="field-hint mb-0">Retour à l'accueil dans quelques secondes</span>
        </div>
    </div>
</body>
</html>
