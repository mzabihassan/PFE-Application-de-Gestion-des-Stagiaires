@extends('layouts.app')

@section('title', 'Connexion')

@section('content')
<div class="auth-wrap">
    <div class="auth-card fade-in">
        <div class="text-center">
            <img src="{{ asset('images/ALTEN-Logo.wine.png') }}" alt="ALTEN" class="auth-logo mx-auto">
            <div class="page-kicker" style="justify-content:center"><i class="bi bi-shield-lock-fill"></i> Espace interne</div>
            <h1 class="h3 mb-2">Bienvenue</h1>
            <p class="text-muted mb-4">Accédez à la plateforme de gestion des stagiaires.</p>
        </div>

        <form action="{{ route('login.perform') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Mot de passe</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
                <label class="form-check-label" for="remember">Se souvenir de moi</label>
            </div>

            <button type="submit" class="btn btn-success w-100">
                <i class="bi bi-box-arrow-in-right"></i> Se connecter
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="{{ route('forgot.options') }}" class="small">Identifiant ou mot de passe oublié ?</a>
        </div>

        <div class="auth-demo">
            <i class="bi bi-info-circle"></i>
            <span>Compte démo admin&nbsp;: <code>admin@internships.local</code> / <code>password123</code></span>
        </div>
    </div>
</div>

@push('scripts')
<style>
    .auth-wrap {
        min-height: calc(100vh - 4rem);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem 0;
    }
    .auth-card {
        width: 100%;
        max-width: 27rem;
        padding: clamp(1.6rem, 4vw, 2.6rem);
        background: var(--surface);
        border: 1px solid var(--hairline);
        border-radius: var(--r-xl);
        box-shadow: var(--sh-lg);
    }
    .auth-card code { font-family: var(--font-mono); font-size: .82em; color: var(--accent-ink); }
    .auth-demo {
        display: flex; align-items: center; gap: .55rem;
        margin-top: 1.6rem; padding: .7rem .85rem;
        font-size: .78rem; color: var(--muted);
        background: var(--surface-2);
        border: 1px solid var(--hairline);
        border-radius: var(--r-sm);
    }
    .auth-demo i { color: var(--accent-ink); }
</style>
@endpush
@endsection
