<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pointage — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-900 text-white flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-6 text-center">
        <p class="text-lg mb-1">Bonsoir</p>
        <h1 class="text-3xl font-bold mb-8">{{ $nom }}</h1>

        <form action="{{ route('kiosque.pointer') }}" method="POST">
            @csrf
            <button type="submit" class="w-full p-6 rounded bg-orange-600 text-lg font-bold">
                Pointer ma présence
            </button>
        </form>

        @if ($cachetsEligibles ?? false)
            <a href="{{ route('kiosque.cachets.index') }}" class="block w-full p-6 mt-4 rounded bg-emerald-600 text-lg font-bold">
                Déclarer mon cachet
            </a>
        @endif

        <a href="{{ route('kiosque.home') }}" class="block mt-6 text-sm text-gray-400">
            Ce n'est pas toi ? Touche ici pour revenir en arrière.
        </a>
    </div>
</body>
</html>
