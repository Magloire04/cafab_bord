<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">Mon profil</h1>
        </div>
    </x-slot>

    <div class="row g-4">
        <div class="col-md-8">
            <div class="content-card mb-4">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="content-card">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>
</x-app-layout>
