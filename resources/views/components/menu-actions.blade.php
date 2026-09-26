{{-- strategy "fixed" : sans elle, le menu serait coupé par l'overflow: hidden de .table-card. --}}
<div class="dropdown d-inline-block">
    <button type="button" class="btn-outline btn-sm menu-actions-declencheur"
            data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}'
            aria-expanded="false" aria-label="Actions">…</button>
    <ul class="dropdown-menu dropdown-menu-end menu-actions">
        {{ $slot }}
    </ul>
</div>
