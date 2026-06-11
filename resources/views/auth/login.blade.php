@extends('layouts.auth')

@section('title', 'Connexion')

@section('content')
<div class="auth">
    {{-- ── Panneau de marque (fond animé géométrique ALTEN) ─────────────── --}}
    @include('partials.auth-brand')

    {{-- ── Panneau formulaire ───────────────────────────────────────────── --}}
    <main class="auth-form-wrap">
        <div class="auth-card">
            <img src="{{ asset('images/alten-logo.webp') }}" alt="ALTEN" class="auth-card-logo">
            <span class="auth-eyebrow"><i class="bi bi-shield-lock-fill"></i> Espace interne</span>
            <h2 class="auth-card-title">Connexion</h2>
            <p class="auth-card-sub">Accédez à votre espace de gestion des stagiaires.</p>

            <form action="{{ route('login.perform') }}" method="POST" novalidate>
                @csrf

                <div class="auth-field">
                    <label for="email" class="auth-label">Adresse e-mail</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-envelope auth-input-icon"></i>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                               class="auth-input" placeholder="prenom.nom@internships.local"
                               autocomplete="username" required autofocus>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password" class="auth-label">Mot de passe</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-lock auth-input-icon"></i>
                        <input type="password" id="password" name="password"
                               class="auth-input has-toggle" placeholder="••••••••"
                               autocomplete="current-password" required>
                        <button type="button" class="auth-toggle" data-toggle-password="password" aria-label="Afficher le mot de passe">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="auth-row">
                    <label class="auth-check">
                        <input type="checkbox" name="remember" value="1">
                        <span>Se souvenir de moi</span>
                    </label>
                    <a href="{{ route('forgot.password') }}" class="auth-link">Mot de passe oublié ?</a>
                </div>

                <button type="submit" class="auth-btn">
                    Se connecter <i class="bi bi-arrow-right"></i>
                </button>
            </form>

            <p class="auth-foot-note">Plateforme réservée au personnel ALTEN.</p>
        </div>
    </main>
</div>
@endsection
