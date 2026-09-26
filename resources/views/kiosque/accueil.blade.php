<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pointage — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-cafab.png') }}">
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="kiosk d-flex flex-column"
         data-kiosque-etat
         data-url="{{ route('kiosque.etat') }}"
         data-en-cours-id="{{ $seance?->id }}"
         data-prochaine-id="{{ $prochaine['id'] ?? '' }}">
        <div class="kiosk-topbar">
            <img src="{{ asset('images/logo-cafab.png') }}" alt="CAFAB" class="kiosk-logo">
            <div class="d-flex align-items-center gap-3">
                @if ($seance)
                    <span class="badge-st st-fort">Séance en cours</span>
                @endif
                <x-horloge variante="kiosque" />
            </div>
        </div>

        <div class="flex-grow-1 d-flex flex-column justify-content-center kiosk-pin-panel">
            <h1 class="kiosk-title mb-2">Tape ton code</h1>
            <p class="field-hint kiosk-subtitle mb-4">
                @if ($seance)
                    Répétition en cours, début prévu {{ \Illuminate\Support\Carbon::parse($seance->heure_prevue)->format('H:i') }}
                @else
                    Aucune répétition en cours pour le moment.
                    @if ($prochaine)
                        <br>{{ $prochaine['texte'] }}.
                    @endif
                @endif
            </p>

            @error('pin')
                <p class="field-error kiosk-error-text mb-3">{{ $message }}</p>
            @enderror

            <form action="{{ route('kiosque.identifier') }}" method="POST" x-data="{ pin: '' }">
                @csrf
                <input type="hidden" name="pin" x-model="pin">

                <div class="d-flex gap-2 mb-4 justify-content-center">
                    <template x-for="i in 4" :key="i">
                        <div class="pin-key pin-dot d-flex align-items-center justify-content-center" x-text="pin.length >= i ? '•' : ''"></div>
                    </template>
                </div>

                <div class="row g-2">
                    @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $chiffre)
                        <div class="col-4">
                            <button type="button" class="pin-key w-100" x-on:click="if (pin.length < 4) pin += '{{ $chiffre }}'">{{ $chiffre }}</button>
                        </div>
                    @endforeach
                    <div class="col-4">
                        <button type="button" class="btn-outline w-100 h-100 justify-content-center" x-on:click="pin = ''">Effacer</button>
                    </div>
                    <div class="col-4">
                        <button type="button" class="pin-key w-100" x-on:click="if (pin.length < 4) pin += '0'">0</button>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn-ink w-100 h-100 justify-content-center" x-bind:disabled="pin.length !== 4">Valider</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="kiosk-bottombar">
            <span class="field-hint mb-0">Tu ne connais pas ton code ? Demande au coach.</span>
            @if ($seance?->coach?->user)
                <span class="field-hint mb-0">Coach de la séance : {{ $seance->coach->user->name }}</span>
            @endif
        </div>
    </div>
</body>
</html>
