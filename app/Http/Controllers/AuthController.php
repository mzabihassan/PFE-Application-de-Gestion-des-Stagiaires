<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $remember)) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'))
                ->with('success', 'Connexion réussie.');
        }

        return back()
            ->withErrors(['email' => 'Identifiants invalides ou compte inactif.'])
            ->onlyInput('email');
    }

    /** Formulaire : demander un lien de réinitialisation (par e-mail). */
    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    /** Envoie un lien de réinitialisation si l'e-mail correspond à un compte actif. */
    public function forgotPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:120'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('is_active', true)
            ->first();

        // On n'envoie le lien que si l'e-mail correspond à un compte existant.
        if ($user !== null) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()],
            );

            $resetUrl = route('password.reset', ['token' => $token, 'email' => $user->email]);
            $user->notify(new ResetPasswordLink($resetUrl));
        }

        // Message neutre (on ne révèle pas si l'adresse existe).
        return back()->with('success', 'Si un compte est associé à cette adresse, un lien de réinitialisation vient d’être envoyé.');
    }

    /** Formulaire : choisir un nouveau mot de passe à partir du lien. */
    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /** Applique le nouveau mot de passe après vérification du jeton. */
    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $validated['email'])->first();

        if ($record === null || ! Hash::check($validated['token'], $record->token)) {
            return back()->with('error', 'Ce lien de réinitialisation est invalide.')->onlyInput('email');
        }

        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

            return redirect()->route('forgot.password')->with('error', 'Ce lien a expiré. Veuillez en demander un nouveau.');
        }

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null) {
            return back()->with('error', 'Aucun compte ne correspond à cette adresse.');
        }

        $user->update(['password_hash' => Hash::make($validated['password'])]);
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return redirect()->route('login')->with('success', 'Mot de passe réinitialisé. Vous pouvez vous connecter.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Déconnexion effectuée.');
    }
}
