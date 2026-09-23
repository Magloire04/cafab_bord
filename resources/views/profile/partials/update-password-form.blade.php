<section>
    <header class="mb-3">
        <h2 class="section-title mb-1">{{ __('Update Password') }}</h2>
        <p class="field-hint mb-0">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        @method('put')

        <div class="mb-3">
            <label for="update_password_current_password" class="field-label">{{ __('Current Password') }}</label>
            <input id="update_password_current_password" name="current_password" type="password" class="field-control" autocomplete="current-password">
            @error('current_password', 'updatePassword') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="update_password_password" class="field-label">{{ __('New Password') }}</label>
            <input id="update_password_password" name="password" type="password" class="field-control" autocomplete="new-password">
            @error('password', 'updatePassword') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="update_password_password_confirmation" class="field-label">{{ __('Confirm Password') }}</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="field-control" autocomplete="new-password">
            @error('password_confirmation', 'updatePassword') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn-ink">{{ __('Save') }}</button>

            @if (session('status') === 'password-updated')
                <p class="field-hint mb-0">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
