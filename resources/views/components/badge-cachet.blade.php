@props(['statut'])

@php
    $config = match ($statut?->value ?? $statut) {
        'du' => ['class' => 'pay-du', 'label' => 'Dû'],
        'declaree_payee' => ['class' => 'pay-decl-ok', 'label' => 'Déclarée payée'],
        'declaree_non_payee' => ['class' => 'pay-decl-no', 'label' => 'Déclarée non payée'],
        'validee_payee' => ['class' => 'pay-valide', 'label' => 'Validée payée'],
        'annule' => ['class' => 'st-absent', 'label' => 'Annulé'],
        default => ['class' => 'st-absent', 'label' => (string) ($statut?->value ?? $statut)],
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge-st '.$config['class']]) }}>
    {{ $config['label'] }}
</span>
