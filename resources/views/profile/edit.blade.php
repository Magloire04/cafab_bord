<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-0">{{ __('Profile') }}</h2>
    </x-slot>

    <div class="row g-4">
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
