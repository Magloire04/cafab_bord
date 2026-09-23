<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Aperçu de l'import</h1>
        </div>
    </x-slot>

    <form action="{{ route('admin.filles.import.confirm') }}" method="POST">
        @csrf

        <div class="table-card mb-4">
            <table>
                <thead>
                    <tr>
                        <th></th>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Contact</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $index => $row)
                        <tr class="{{ $row['doublon'] ? 'pending' : '' }}">
                            <td>
                                <input type="checkbox" name="lignes[]" value="{{ $index }}" class="field-check"
                                       {{ $row['doublon'] ? '' : 'checked' }}>
                            </td>
                            <td>{{ $row['nom'] }}</td>
                            <td>{{ $row['prenom'] }}</td>
                            <td>{{ $row['contact'] ?? '—' }}</td>
                            <td>
                                @if ($row['doublon'])
                                    <span class="badge-st st-retard">doublon potentiel</span>
                                @else
                                    <span class="badge-st st-heure">nouvelle fiche</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn-ink justify-content-center">Confirmer l'import des lignes cochées</button>
        </div>
    </form>
</x-app-layout>
