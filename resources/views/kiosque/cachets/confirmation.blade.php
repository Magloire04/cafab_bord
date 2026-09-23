<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pointage — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-cafab.png') }}">
    <meta http-equiv="refresh" content="5;url={{ route('kiosque.home') }}">
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="kiosk-confirm-shell">
        <div class="kiosk-progress kiosk-progress-5s"></div>

        <div class="flex-grow-1 d-flex align-items-center justify-content-center">
            <div class="text-center kiosk-confirm-panel">
                @if ($erreur ?? false)
                    <div class="kiosk-confirm-icon is-error">!</div>
                    <h1 class="kiosk-title mb-2">{{ $nom }}, un instant.</h1>
                    <p class="field-hint">{{ $erreur }}</p>
                @else
                    <div class="kiosk-confirm-icon">✓</div>
                    <h1 class="kiosk-title mb-2">Merci, {{ $nom }}.</h1>
                    <p class="field-hint">Déclaration enregistrée.</p>
                @endif
            </div>
        </div>

        <div class="kiosk-bottombar justify-content-center">
            <span class="field-hint mb-0">Retour à l'accueil dans quelques secondes…</span>
        </div>
    </div>
</body>
</html>
