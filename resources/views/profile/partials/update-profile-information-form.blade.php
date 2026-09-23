<section>
    <header class="mb-3">
        <h2 class="section-title mb-1">{{ __('Profile Information') }}</h2>
        <p class="field-hint mb-0">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="mb-3">
            <label for="name" class="field-label">{{ __('Name') }}</label>
            <input id="name" name="name" type="text" class="field-control" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
            @error('name') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="field-label">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" class="field-control" value="{{ old('email', $user->email) }}" required autocomplete="username">
            @error('email') <div class="field-error">{{ $message }}</div> @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-2">
                    <p class="field-hint mb-2">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="btn-outline btn-sm">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="field-hint mb-0">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn-ink">{{ __('Save') }}</button>

            @if (session('status') === 'profile-updated')
                <p class="field-hint mb-0">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
