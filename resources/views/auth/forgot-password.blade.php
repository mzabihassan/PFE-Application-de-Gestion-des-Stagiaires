@extends('layouts.auth')

@section('title', 'Mot de passe oublié')

@section('content')
<div class="auth">
    @include('partials.auth-brand')

    <main class="auth-form-wrap">
        <div class="auth-card">
            <img src="{{ asset('images/alten-logo.webp') }}" alt="ALTEN" class="auth-card-logo">
            <span class="auth-eyebrow"><i class="bi bi-key-fill"></i> Sécurité du compte</span>
            <h2 class="auth-card-title">Mot de passe oublié</h2>
            <p class="auth-card-sub">Saisissez votre adresse e-mail : si un compte y est associé, vous recevrez un lien de réinitialisation.</p>

            <form action="{{ route('forgot.password.perform') }}" method="POST" novalidate>
                @csrf

                <div class="auth-field">
                    <label for="email" class="auth-label">Adresse e-mail</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-envelope auth-input-icon"></i>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                               class="auth-input" placeholder="prenom.nom@internships.local"
                               autocomplete="email" required autofocus>
                    </div>
                </div>

                <button type="submit" class="auth-btn" style="margin-top:.4rem">
                    <i class="bi bi-send"></i> Envoyer le lien
                </button>
            </form>

            <p class="auth-foot-note">
                <a href="{{ route('login') }}" class="auth-link auth-back"><i class="bi bi-arrow-left"></i> Retour à la connexion</a>
            </p>
        </div>
    </main>
</div>
@endsection
