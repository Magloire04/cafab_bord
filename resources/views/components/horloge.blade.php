@props(['variante' => 'sidebar'])
<p {{ $attributes->merge(['class' => 'horloge horloge-'.$variante]) }}
   data-horloge
   data-serveur-ms="{{ now()->getTimestampMs() }}"
   data-fuseau="{{ config('app.timezone') }}">{{ now()->translatedFormat('l j F Y') }} · {{ now()->format('H:i:s') }}</p>
