@php
    $estAdmin = auth()->user()->role === \App\Enums\UserRole::Admin;
    $onglets = [['libelle' => 'Planning récurrent', 'url' => route('plannings.index'), 'actif' => request()->routeIs('plannings.*')]];
    if ($estAdmin) {
        $onglets[] = ['libelle' => 'Calendrier', 'url' => route('admin.calendrier'), 'actif' => request()->routeIs('admin.calendrier')];
        $onglets[] = ['libelle' => 'Pointages', 'url' => route('admin.pointages.index'), 'actif' => request()->routeIs('admin.pointages.*')];
    }
    $onglets[] = ['libelle' => 'Séance extraordinaire', 'url' => route('seances.create-extraordinaire'), 'actif' => request()->routeIs('seances.create-extraordinaire')];
@endphp
<x-onglets :onglets="$onglets" />
