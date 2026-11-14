<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\VerificationCode;
use App\Support\Gmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * Registro e ingreso de clientes. Reglas:
 *  - Solo correos @gmail.com.
 *  - El Gmail se confirma con un código de 6 dígitos antes de poder entrar.
 *  - "Olvidé mi contraseña" también funciona con código al Gmail.
 */
class AuthController extends Controller
{
    private const SESSION_VERIFY = 'verify_customer_id';
    private const SESSION_RESET = 'reset_email';

    // ───────────────────────── Registro ─────────────────────────

    public function showRegister()
    {
        return view('customer.auth.register');
    }

    public function register(Request $request)
    {
        Gmail::normalize($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s-]{7,20}$/'],
            'email' => Gmail::rules([Rule::unique('customers', 'email')->whereNotNull('email_verified_at')]),
            'city' => ['nullable', 'string', 'max:80'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'terms' => ['accepted'],
        ], [
            'email.regex' => Gmail::MESSAGE,
            'email.unique' => 'Ese Gmail ya tiene una cuenta. Inicia sesión o recupera tu contraseña.',
            'phone.regex' => 'Escribe un número de celular válido (solo números).',
            'terms.accepted' => 'Debes aceptar el uso de tus datos para contactarte.',
        ], ['name' => 'nombre', 'phone' => 'celular', 'email' => 'Gmail', 'password' => 'contraseña']);

        $data['phone'] = preg_replace('/\D+/', '', $data['phone']);
        unset($data['terms']);

        // Reutilizar: registro anterior sin verificar con el mismo Gmail, o ficha creada por el equipo en una venta
        $customer = Customer::where('email', $data['email'])->whereNull('email_verified_at')->first()
            ?? Customer::whereNull('password')->where('phone', $data['phone'])->first();

        if ($customer) {
            $customer->update([...$data, 'email_verified_at' => null]);
        } else {
            $customer = Customer::create($data);
        }

        return $this->sendVerification($request, $customer, 'Te enviamos un código de 6 dígitos a tu Gmail.');
    }

    // ───────────────────────── Verificación del Gmail ─────────────────────────

    public function showVerify(Request $request)
    {
        $customer = $this->pendingCustomer($request);
        if (! $customer) {
            return redirect()->route('customer.login');
        }

        return view('customer.auth.verify', ['email' => Gmail::mask($customer->email)]);
    }

    public function verify(Request $request)
    {
        $customer = $this->pendingCustomer($request);
        if (! $customer) {
            return redirect()->route('customer.login')->with('status', 'La verificación expiró. Inicia sesión de nuevo.');
        }

        $request->validate(['code' => ['required', 'string', 'max:10']], [], ['code' => 'código']);

        if ($error = VerificationCode::check($customer, VerificationCode::VERIFY, $request->code)) {
            return back()->withErrors(['code' => $error]);
        }

        $customer->forceFill(['email_verified_at' => now(), 'last_login_at' => now()])->save();
        $request->session()->forget(self::SESSION_VERIFY);

        Auth::guard('customer')->login($customer, true);
        $request->session()->regenerate();
        $customer->adoptVisitorLikes($request->attributes->get('visitor_token'));

        return redirect()->intended(route('customer.account'))
            ->with('status', "¡Gmail verificado! Bienvenido, {$customer->firstName()}.");
    }

    public function resendVerification(Request $request)
    {
        $customer = $this->pendingCustomer($request);
        if (! $customer) {
            return redirect()->route('customer.login');
        }

        return $this->sendVerification($request, $customer, 'Te enviamos un código nuevo.');
    }

    // ───────────────────────── Ingreso ─────────────────────────

    public function showLogin()
    {
        return view('customer.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
        ], [], ['login' => 'Gmail o celular', 'password' => 'contraseña']);

        $field = str_contains($data['login'], '@') ? 'email' : 'phone';
        $value = $field === 'phone' ? preg_replace('/\D+/', '', $data['login']) : strtolower(trim($data['login']));

        $key = 'customer-login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return back()->withInput($request->only('login'))
                ->withErrors(['login' => 'Demasiados intentos. Espera '.RateLimiter::availableIn($key).' segundos.']);
        }

        if (! Auth::guard('customer')->validate([$field => $value, 'password' => $data['password'], 'is_active' => true])) {
            RateLimiter::hit($key, 300);

            return back()->withInput($request->only('login'))
                ->withErrors(['login' => 'Datos incorrectos. Revisa tu Gmail/celular y contraseña.']);
        }
        RateLimiter::clear($key);

        $customer = Customer::where($field, $value)->firstOrFail();

        // Gmail sin verificar: se manda el código y no se entra hasta confirmarlo
        if (! $customer->email_verified_at) {
            if (! $customer->email || ! preg_match('/@gmail\.com$/', $customer->email)) {
                return back()->withInput($request->only('login'))
                    ->withErrors(['login' => 'Tu cuenta necesita un Gmail. Escríbenos por WhatsApp para actualizarla.']);
            }

            return $this->sendVerification($request, $customer, 'Antes de entrar, confirma tu Gmail con el código que te enviamos.');
        }

        Auth::guard('customer')->login($customer, $request->boolean('remember'));
        $request->session()->regenerate();
        $customer->update(['last_login_at' => now()]);
        $customer->adoptVisitorLikes($request->attributes->get('visitor_token'));

        return redirect()->intended(route('customer.account'))->with('status', "Hola de nuevo, {$customer->firstName()} 👋");
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Sesión cerrada. ¡Vuelve pronto!');
    }

    // ───────────────────────── Recuperar contraseña ─────────────────────────

    public function showForgot()
    {
        return view('customer.auth.forgot');
    }

    public function sendReset(Request $request)
    {
        Gmail::normalize($request);
        $request->validate(['email' => Gmail::rules()], ['email.regex' => Gmail::MESSAGE], ['email' => 'Gmail']);

        $key = 'reset:'.$request->email;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->withInput()->withErrors(['email' => 'Espera '.RateLimiter::availableIn($key).' segundos para pedir otro código.']);
        }
        RateLimiter::hit($key, 60);

        $customer = Customer::where('email', $request->email)->whereNotNull('password')->where('is_active', true)->first();
        if ($customer) {
            try {
                VerificationCode::issue($customer, VerificationCode::RESET);
            } catch (Throwable $e) {
                Log::error('No se pudo enviar el código de recuperación: '.$e->getMessage());

                return back()->withInput()->withErrors(['email' => 'No pudimos enviar el correo en este momento. Intenta en unos minutos.']);
            }
        }

        // Mismo mensaje exista o no la cuenta (no revela qué correos están registrados)
        $request->session()->put(self::SESSION_RESET, $request->email);

        return redirect()->route('customer.password.reset')
            ->with('status', 'Si ese Gmail tiene una cuenta, te llegó un código de 6 dígitos.');
    }

    public function showReset(Request $request)
    {
        $email = $request->session()->get(self::SESSION_RESET);
        if (! $email) {
            return redirect()->route('customer.password.forgot');
        }

        return view('customer.auth.reset', ['email' => Gmail::mask($email)]);
    }

    public function reset(Request $request)
    {
        $email = $request->session()->get(self::SESSION_RESET);
        if (! $email) {
            return redirect()->route('customer.password.forgot');
        }

        $request->validate([
            'code' => ['required', 'string', 'max:10'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ], [], ['code' => 'código', 'password' => 'nueva contraseña']);

        $customer = Customer::where('email', $email)->whereNotNull('password')->first();
        $error = $customer ? VerificationCode::check($customer, VerificationCode::RESET, $request->code) : 'Código incorrecto.';
        if ($error) {
            return back()->withErrors(['code' => $error]);
        }

        // Quien recibió el código en su Gmail también queda verificado
        $customer->forceFill([
            'password' => $request->password,
            'email_verified_at' => $customer->email_verified_at ?? now(),
            'last_login_at' => now(),
        ])->save();
        $request->session()->forget(self::SESSION_RESET);

        Auth::guard('customer')->login($customer, true);
        $request->session()->regenerate();

        return redirect()->route('customer.account')->with('status', 'Contraseña cambiada. ¡Listo!');
    }

    // ───────────────────────── Ayudantes ─────────────────────────

    private function pendingCustomer(Request $request): ?Customer
    {
        $id = $request->session()->get(self::SESSION_VERIFY);

        return $id ? Customer::whereKey($id)->whereNull('email_verified_at')->first() : null;
    }

    private function sendVerification(Request $request, Customer $customer, string $message)
    {
        $request->session()->put(self::SESSION_VERIFY, $customer->id);

        $key = 'verify-send:'.$customer->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return redirect()->route('customer.verify')
                ->withErrors(['code' => 'Espera '.RateLimiter::availableIn($key).' segundos para pedir otro código.']);
        }
        RateLimiter::hit($key, 60);

        try {
            VerificationCode::issue($customer, VerificationCode::VERIFY);
        } catch (Throwable $e) {
            Log::error('No se pudo enviar el código de verificación: '.$e->getMessage());

            return redirect()->route('customer.verify')
                ->withErrors(['code' => 'No pudimos enviar el correo en este momento. Pulsa "Reenviar código" en unos minutos.']);
        }

        return redirect()->route('customer.verify')->with('status', $message);
    }
}
