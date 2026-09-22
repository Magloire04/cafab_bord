<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pointage — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen"
      x-data="{}" x-init="setTimeout(() => window.location = '{{ route('kiosque.home') }}', 4000)">
    <div class="w-full max-w-md p-6 text-center">
        @if (isset($erreur))
            <h1 class="text-2xl font-bold mb-4">{{ $nom }}, un instant.</h1>
            <p class="mb-4">{{ $erreur }}</p>
        @else
            <h1 class="text-2xl font-bold mb-4">C'est noté, {{ $nom }}.</h1>
            <p>Arrivée enregistrée à {{ $pointage->pointe_a->format('H:i') }}.</p>
        @endif
        <p class="mt-8 text-sm text-gray-500">Retour à l'accueil dans quelques secondes…</p>
    </div>
</body>
</html>
