@php
    $onglets = [];
    if (auth()->user()->role === \App\Enums\UserRole::Admin) {
        $onglets[] = ['libelle' => 'Coachs', 'url' => route('admin.coaches.index'), 'actif' => request()->routeIs('admin.coaches.*')];
    }
    $onglets[] = ['libelle' => 'Filles', 'url' => route('filles.index'), 'actif' => request()->routeIs('filles.*')];
@endphp
<x-onglets :onglets="$onglets" />
