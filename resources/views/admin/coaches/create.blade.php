<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Ajouter un coach</h1>
        </div>
    </x-slot>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="content-card">
                <form action="{{ route('admin.coaches.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="field-label">Nom complet</label>
                        <input id="name" name="name" type="text" class="field-control" value="{{ old('name') }}" required>
                        @error('name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="field-label">E-mail</label>
                        <input id="email" name="email" type="email" class="field-control" value="{{ old('email') }}" required>
                        @error('email') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="contact" class="field-label">Contact</label>
                        <input id="contact" name="contact" type="text" class="field-control" value="{{ old('contact') }}">
                    </div>

                    <div class="mb-4">
                        <label for="date_entree" class="field-label">Date d'entrée</label>
                        <input id="date_entree" name="date_entree" type="date" class="field-control" value="{{ old('date_entree') }}" required>
                        @error('date_entree') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn-ink justify-content-center">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
