<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pointage — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <meta http-equiv="refresh" content="5;url={{ route('kiosque.home') }}">
</head>
<body class="bg-gray-900 text-white flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-6 text-center">
        @if ($erreur ?? false)
            <h1 class="text-2xl font-bold mb-4">{{ $nom }}, un instant.</h1>
            <p>{{ $erreur }}</p>
        @else
            <h1 class="text-2xl font-bold mb-4">Merci, {{ $nom }}.</h1>
            <p>Déclaration enregistrée.</p>
        @endif
        <p class="mt-6 text-sm text-gray-400">Retour à l'accueil dans quelques secondes…</p>
    </div>
</body>
</html>
