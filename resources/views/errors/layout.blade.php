<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-cafab.png') }}">
    @vite(['resources/css/app.scss'])
</head>
<body>
    {{-- Aucune requête en base ici (ni horloge, ni session) : la page doit
         s'afficher même quand la base de données ne répond pas. --}}
    <div class="auth-shell">
        <div class="auth-card">
            <div class="logo-box">
                <img src="{{ asset('images/logo-cafab.png') }}" alt="CAFAB">
            </div>
            <p class="overline mb-1">Présence &amp; paiements</p>
            <h1 class="page-title">@yield('titre')</h1>
            <p class="field-hint mb-4">@yield('message')</p>

            @unless ($sansRetour ?? false)
                <a href="{{ url('/') }}" class="btn-ink w-100 justify-content-center">{{ "Retour à l'accueil" }}</a>
            @endunless
        </div>
    </div>
</body>
</html>
