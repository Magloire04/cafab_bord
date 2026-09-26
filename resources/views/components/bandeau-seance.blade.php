@php($etat = app(\App\Services\EtatSeances::class)->pourUtilisateur(auth()->user()))
<div class="bandeau-seance" data-bandeau-seance data-url="{{ route('etat-seances') }}" @if ($etat['lignes'] === []) hidden @endif>
    <div class="bandeau-seance-lignes" data-bandeau-lignes>
        @foreach ($etat['lignes'] as $ligne)
            <p class="bandeau-ligne {{ $ligne['type'] === 'en_cours' ? 'bandeau-en-cours' : 'bandeau-prochaine' }}">{{ $ligne['texte'] }}</p>
        @endforeach
    </div>
    <button type="button" class="btn-outline btn-sm" data-bandeau-notifications hidden>Activer les notifications</button>
</div>
