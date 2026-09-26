@props(['variante' => 'sidebar'])
<p {{ $attributes->merge(['class' => 'horloge horloge-'.$variante]) }}
   data-horloge
   data-serveur-ms="{{ now()->getTimestampMs() }}"
   data-fuseau="{{ config('app.timezone') }}"><span class="horloge-date">{{ now()->translatedFormat('l j F Y') }}</span><span class="horloge-sep"> · </span><span class="horloge-heure">{{ now()->format('H:i:s') }}</span></p>
