@extends('layouts.auth')

@section('title', 'Nouveau mot de passe')

@section('content')
<div class="auth">
    @include('partials.auth-brand')

    <main class="auth-form-wrap">
        <div class="auth-card">
            <img src="{{ asset('images/alten-logo.webp') }}" alt="ALTEN" class="auth-card-logo">
            <span class="auth-eyebrow"><i class="bi bi-shield-lock-fill"></i> Réinitialisation</span>
            <h2 class="auth-card-title">Nouveau mot de passe</h2>
            <p class="auth-card-sub">Choisissez un nouveau mot de passe pour votre compte.</p>

            <form action="{{ route('password.update') }}" method="POST" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="auth-field">
                    <label for="email" class="auth-label">Adresse e-mail</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-envelope auth-input-icon"></i>
                        <input type="email" id="email" name="email" value="{{ old('email', $email) }}"
                               class="auth-input" placeholder="prenom.nom@internships.local"
                               autocomplete="email" required readonly>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password" class="auth-label">Nouveau mot de passe</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-lock auth-input-icon"></i>
                        <input type="password" id="password" name="password"
                               class="auth-input has-toggle" placeholder="••••••••" autocomplete="new-password" required autofocus>
                        <button type="button" class="auth-toggle" data-toggle-password="password" aria-label="Afficher le mot de passe">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password_confirmation" class="auth-label">Confirmer le mot de passe</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-lock-fill auth-input-icon"></i>
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               class="auth-input has-toggle" placeholder="••••••••" autocomplete="new-password" required>
                        <button type="button" class="auth-toggle" data-toggle-password="password_confirmation" aria-label="Afficher le mot de passe">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="auth-btn" style="margin-top:.4rem">
                    <i class="bi bi-shield-check"></i> Réinitialiser le mot de passe
                </button>
            </form>

            <p class="auth-foot-note">
                <a href="{{ route('login') }}" class="auth-link auth-back"><i class="bi bi-arrow-left"></i> Retour à la connexion</a>
            </p>
        </div>
    </main>
</div>
@endsection
