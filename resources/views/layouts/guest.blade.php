<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-cafab.png') }}">

    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
