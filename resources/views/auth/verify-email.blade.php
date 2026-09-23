<x-guest-layout>
    <div class="logo-box">
        <img src="{{ asset('images/logo-cafab.png') }}" alt="CAFAB">
    </div>

    <p class="overline mb-1">Présence &amp; paiements</p>
    <h1 class="page-title">Vérifier l'adresse email</h1>

    <p class="field-hint mt-2 mb-0">
        Avant de commencer, confirme ton adresse email en cliquant sur le lien que nous venons de t'envoyer. Si tu ne l'as pas reçu, nous pouvons te le renvoyer.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="callout-success mt-3">
            <span class="fw-semibold">Un nouveau lien de vérification a été envoyé à ton adresse email.</span>
        </div>
    @endif

    <div class="mt-4 d-flex align-items-center justify-content-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <button type="submit" class="btn-ink">
                Renvoyer le lien de vérification
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="field-hint border-0 bg-transparent p-0">
                Déconnexion
            </button>
        </form>
    </div>
</x-guest-layout>
