<x-onglets :onglets="[
    ['libelle' => 'Prestations', 'url' => route('admin.prestations.index'), 'actif' => request()->routeIs('admin.prestations.*')],
    ['libelle' => 'Paiements', 'url' => route('admin.paiements.index'), 'actif' => request()->routeIs('admin.paiements.*')],
]" />
