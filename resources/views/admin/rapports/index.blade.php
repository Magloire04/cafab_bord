<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Rapports</h1>
            <p class="field-hint mb-0">Ponctualité et dépenses de prestations, exportables en Excel.</p>
        </div>
    </x-slot>

    <div class="row g-3">
        <div class="col-md-6">
            <a href="{{ route('admin.rapports.ponctualite') }}" class="link-tile h-100">
                <p class="link-tile-title">Rapport de ponctualité</p>
                <p class="link-tile-desc">Présences, retards et absences des coachs et des filles.</p>
            </a>
        </div>
        <div class="col-md-6">
            <a href="{{ route('admin.rapports.depenses') }}" class="link-tile h-100">
                <p class="link-tile-title">Rapport des dépenses de prestations</p>
                <p class="link-tile-desc">Cachets validés payés, par prestation et par fille.</p>
            </a>
        </div>
    </div>
</x-app-layout>
