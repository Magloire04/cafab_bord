<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">Rapports</h2>
        <p class="text-muted mb-0">Ponctualité et dépenses de prestations, exportables en Excel.</p>
    </x-slot>

    <div class="row g-4">
        <div class="col-md-6">
            <a href="{{ route('admin.rapports.ponctualite') }}" class="text-decoration-none text-dark">
                <div class="card p-4 shadow-sm h-100">
                    <h5 class="fw-bold"><i class="fas fa-clock text-primary me-2"></i>Rapport de ponctualité</h5>
                    <p class="text-muted mb-0">Présences, retards et absences des coachs et des filles.</p>
                </div>
            </a>
        </div>
        <div class="col-md-6">
            <a href="{{ route('admin.rapports.depenses') }}" class="text-decoration-none text-dark">
                <div class="card p-4 shadow-sm h-100">
                    <h5 class="fw-bold"><i class="fas fa-sack-dollar text-primary me-2"></i>Rapport des dépenses de prestations</h5>
                    <p class="text-muted mb-0">Cachets validés payés, par prestation et par fille.</p>
                </div>
            </a>
        </div>
    </div>
</x-app-layout>
