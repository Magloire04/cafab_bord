@props(['onglets'])
<nav class="onglets mb-4" aria-label="Sous-navigation">
    @foreach ($onglets as $onglet)
        <a href="{{ $onglet['url'] }}" @class(['onglet', 'active' => $onglet['actif']]) @if ($onglet['actif']) aria-current="page" @endif>
            {{ $onglet['libelle'] }}
        </a>
    @endforeach
</nav>
