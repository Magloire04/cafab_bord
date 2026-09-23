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
        <h1 class="text-2xl font-bold mb-8">{{ $nom }}</h1>

        @forelse ($cachets as $cachet)
            <div class="mb-6 border border-gray-700 rounded p-4">
                <p class="mb-3">{{ $cachet->prestation->titre }} — {{ $cachet->prestation->date->format('d/m/Y') }}</p>
                <form action="{{ route('kiosque.cachets.declarer', $cachet) }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="recu" value="1">
                    <button type="submit" class="p-4 rounded bg-emerald-600 font-bold">J'ai reçu</button>
                </form>
                <form action="{{ route('kiosque.cachets.declarer', $cachet) }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="recu" value="0">
                    <button type="submit" class="p-4 rounded bg-red-700 font-bold">Je n'ai pas reçu</button>
                </form>
            </div>
        @empty
            <p>Aucun cachet à déclarer pour le moment.</p>
        @endforelse

        <a href="{{ route('kiosque.menu') }}" class="block mt-6 text-sm text-gray-400">Retour</a>
    </div>
</body>
</html>
