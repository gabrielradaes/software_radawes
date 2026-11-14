<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationCode;
use App\Support\Gmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Throwable;

/** Ingreso al panel (admin, supervisores, vendedores) y recuperación de contraseña con código al Gmail. */
class AuthController extends Controller
{
    private const SESSION_RESET = 'admin_reset_email';

    public function show()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [], ['email' => 'correo', 'password' => 'contraseña']);
        $credentials['email'] = strtolower(trim($credentials['email']));

        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Credenciales incorrectas o usuario inactivo.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    // ───────────── Recuperar contraseña ─────────────

    public function showForgot()
    {
        return view('admin.auth.forgot');
    }

    public function sendReset(Request $request)
    {
        Gmail::normalize($request);
        $request->validate(['email' => ['required', 'email', 'max:150']], [], ['email' => 'correo']);

        $key = 'admin-reset:'.$request->email;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->withInput()->withErrors(['email' => 'Espera '.RateLimiter::availableIn($key).' segundos para pedir otro código.']);
        }
        RateLimiter::hit($key, 60);

        $user = User::where('email', $request->email)->where('is_active', true)->first();
        if ($user) {
            try {
                VerificationCode::issue($user, VerificationCode::RESET);
            } catch (Throwable $e) {
                Log::error('No se pudo enviar el código de recuperación del panel: '.$e->getMessage());

                return back()->withInput()->withErrors(['email' => 'No pudimos enviar el correo en este momento. Intenta en unos minutos.']);
            }
        }

        // Mismo mensaje exista o no (no revela qué correos tienen acceso al panel)
        $request->session()->put(self::SESSION_RESET, $request->email);

        return redirect()->route('admin.password.reset')
            ->with('status', 'Si ese correo tiene acceso al panel, te llegó un código de 6 dígitos.');
    }

    public function showReset(Request $request)
    {
        $email = $request->session()->get(self::SESSION_RESET);
        if (! $email) {
            return redirect()->route('admin.password.forgot');
        }

        return view('admin.auth.reset', ['email' => Gmail::mask($email)]);
    }

    public function reset(Request $request)
    {
        $email = $request->session()->get(self::SESSION_RESET);
        if (! $email) {
            return redirect()->route('admin.password.forgot');
        }

        $request->validate([
            'code' => ['required', 'string', 'max:10'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['code' => 'código', 'password' => 'nueva contraseña']);

        $user = User::where('email', $email)->where('is_active', true)->first();
        $error = $user ? VerificationCode::check($user, VerificationCode::RESET, $request->code) : 'Código incorrecto.';
        if ($error) {
            return back()->withErrors(['code' => $error]);
        }

        $user->forceFill(['password' => $request->password, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        $request->session()->forget(self::SESSION_RESET);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard')->with('status', 'Contraseña cambiada. ¡Bienvenido, '.$user->firstName().'!');
    }
}
