<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Créer une prestation</h1>
            <p class="field-hint mb-0">Renseigne les informations de la prestation et affecte les filles participantes.</p>
        </div>
    </x-slot>

    <div class="content-card">
        <form action="{{ route('admin.prestations.store') }}" method="POST">
            @csrf

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="titre" class="field-label">Titre</label>
                    <input id="titre" name="titre" type="text" class="field-control" value="{{ old('titre') }}" required>
                    @error('titre') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label for="lieu" class="field-label">Lieu</label>
                    <input id="lieu" name="lieu" type="text" class="field-control" value="{{ old('lieu') }}" required>
                    @error('lieu') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="date" class="field-label">Date</label>
                    <input id="date" name="date" type="date" class="field-control" value="{{ old('date') }}" required>
                    @error('date') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label for="montant_defaut" class="field-label">Montant par défaut du cachet</label>
                    <input id="montant_defaut" name="montant_defaut" type="number" step="0.01" class="field-control" value="{{ old('montant_defaut') }}" required>
                    @error('montant_defaut') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <fieldset class="mb-2">
                <legend class="section-title mb-2">Filles participantes</legend>

                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th class="text-center">Affecter</th>
                                <th>Fille</th>
                                <th>Montant (optionnel, sinon le montant par défaut)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filles as $fille)
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" class="field-check" name="fille_ids[]" value="{{ $fille->id }}">
                                    </td>
                                    <td>{{ $fille->prenom }} {{ $fille->nom }}</td>
                                    <td>
                                        <input type="number" step="0.01" name="montants[{{ $fille->id }}]" class="field-control">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </fieldset>
            @error('fille_ids') <div class="field-error mb-3">{{ $message }}</div> @enderror

            <div class="mt-4">
                <button type="submit" class="btn-ink">
                    Créer
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
