@props(['id', 'name', 'autocomplete' => 'current-password', 'required' => true])
<div class="d-flex gap-2">
    <input id="{{ $id }}" name="{{ $name }}" type="password" autocomplete="{{ $autocomplete }}"
           @required($required) {{ $attributes->merge(['class' => 'field-control']) }}>
    <button type="button" class="btn-outline" data-toggle-password="{{ $id }}" aria-label="Afficher le mot de passe">
        <i class="fas fa-eye"></i>
    </button>
</div>
