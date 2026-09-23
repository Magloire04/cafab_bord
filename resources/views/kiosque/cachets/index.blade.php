<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Déclaration des cachets — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-cafab.png') }}">
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="kiosk d-flex flex-column">
        <div class="kiosk-topbar">
            <div>
                <h1 class="kiosk-title kiosk-title-sm mb-1">Tes cachets, {{ $nom }}</h1>
                <p class="field-hint mb-0">Dis-nous si tu as reçu l'argent. Le bureau vérifiera ensuite.</p>
            </div>
            <a href="{{ route('kiosque.menu') }}" class="btn-outline">Terminer</a>
        </div>

        <div class="flex-grow-1 d-flex flex-column gap-3">
            @forelse ($cachets as $cachet)
                <div class="kiosk-cachet-row">
                    <div>
                        <p class="kiosk-cachet-title mb-0">{{ $cachet->prestation->titre }}</p>
                        <p class="field-hint mb-0">{{ $cachet->prestation->lieu }} · {{ $cachet->prestation->date->translatedFormat('l j F') }}</p>
                    </div>

                    <div class="text-center">
                        <p class="overline mb-1">Cachet</p>
                        <p class="fw-bold time kiosk-cachet-amount mb-0">{{ number_format((float) $cachet->montant, 0, ',', ' ') }} F</p>
                    </div>

                    <div class="d-flex gap-2">
                        <form action="{{ route('kiosque.cachets.declarer', $cachet) }}" method="POST">
                            @csrf
                            <input type="hidden" name="recu" value="1">
                            <button type="submit" class="btn-kiosk-yes">J'ai reçu</button>
                        </form>
                        <form action="{{ route('kiosque.cachets.declarer', $cachet) }}" method="POST">
                            @csrf
                            <input type="hidden" name="recu" value="0">
                            <button type="submit" class="btn-kiosk-no">Pas encore</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="kiosk-cachet-row">
                    <p class="field-hint mb-0">Aucun cachet à déclarer pour le moment.</p>
                </div>
            @endforelse
        </div>

        <div class="kiosk-bottombar">
            <span class="field-hint mb-0">Une déclaration peut être corrigée par le bureau. En cas de doute, parles-en au coach.</span>
        </div>
    </div>
</body>
</html>
