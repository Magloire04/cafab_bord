@props(['statut', 'minutes' => null])

@php
    $config = match ($statut?->value ?? $statut) {
        'a_l_heure' => ['class' => 'st-heure', 'label' => 'À l\'heure'],
        'en_retard' => ['class' => 'st-retard', 'label' => 'En retard'],
        'absent' => ['class' => 'st-absent', 'label' => 'Absent'],
        default => ['class' => 'st-absent', 'label' => 'Pas encore pointée'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge-st '.$config['class']]) }}>
    {{ $config['label'] }}{{ $minutes ? ' · '.$minutes.' min' : '' }}
</span>
