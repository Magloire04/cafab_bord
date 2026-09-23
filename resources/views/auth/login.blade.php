<x-guest-layout>
    <div class="card shadow-lg border-0 rounded-card-xl">
        <div class="card-body p-5 text-center">
            <div class="mb-4">
                <img src="{{ asset('images/logo-cafab.png') }}" alt="CAFAB" class="login-logo">
                <div class="badge bg-primary-subtle text-primary text-uppercase px-3 py-2">Présence &amp; Paiements</div>
            </div>
            <h4 class="mb-4 fw-bold">Connexion</h4>

            <x-auth-session-status class="mb-4 text-start" :status="session('status')" />

            @if ($errors->any())
                <div class="alert alert-danger border-0 shadow-sm">
                    <i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3 text-start">
                    <label for="email" class="form-label fw-bold">Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-user-circle text-muted"></i></span>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                            class="form-control border-start-0" placeholder="Entrez votre email"
                            required autofocus autocomplete="username">
                    </div>
                </div>

                <div class="mb-3 text-start">
                    <label for="password" class="form-label fw-bold">Mot de passe</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                        <input type="password" name="password" id="password"
                            class="form-control border-start-0 border-end-0" placeholder="••••••••"
                            required autocomplete="current-password">
                        <button class="btn btn-outline-light border border-start-0 text-muted bg-white js-toggle-password toggle-password-btn"
                            type="button">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4 text-start form-check">
                    <input class="form-check-input" type="checkbox" id="remember_me" name="remember">
                    <label class="form-check-label small text-muted" for="remember_me">Se souvenir de moi</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow-sm">
                    <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                </button>

                @if (Route::has('password.request'))
                    <a class="d-block mt-3 small text-muted text-decoration-none" href="{{ route('password.request') }}">
                        Mot de passe oublié ?
                    </a>
                @endif
            </form>
        </div>
    </div>
</x-guest-layout>
