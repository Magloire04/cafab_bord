<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pointage — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-6">
        <h1 class="text-2xl font-bold mb-2">Tape ton code</h1>
        @if ($seance)
            <p class="mb-4">Répétition en cours — début prévu {{ \Illuminate\Support\Carbon::parse($seance->heure_prevue)->format('H:i') }}</p>
        @else
            <p class="mb-4">Aucune répétition en cours pour le moment.</p>
        @endif

        @error('pin')
            <p class="text-red-600 mb-4">{{ $message }}</p>
        @enderror

        <form action="{{ route('kiosque.identifier') }}" method="POST" x-data="{ pin: '' }">
            @csrf
            <input type="hidden" name="pin" x-model="pin">
            <div class="text-3xl text-center tracking-widest mb-4" x-text="pin.padEnd(4, '_')"></div>
            <div class="grid grid-cols-3 gap-2">
                @foreach ([1,2,3,4,5,6,7,8,9] as $chiffre)
                    <button type="button" class="p-4 text-xl border rounded"
                            x-on:click="if (pin.length < 4) pin += '{{ $chiffre }}'">{{ $chiffre }}</button>
                @endforeach
                <button type="button" class="p-4 border rounded" x-on:click="pin = ''">Effacer</button>
                <button type="button" class="p-4 text-xl border rounded"
                        x-on:click="if (pin.length < 4) pin += '0'">0</button>
                <button type="submit" class="p-4 border rounded bg-black text-white" x-bind:disabled="pin.length !== 4">Valider</button>
            </div>
        </form>
    </div>
</body>
</html>
