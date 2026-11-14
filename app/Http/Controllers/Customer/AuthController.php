<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('customer.auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s-]{7,20}$/'],
            'email' => ['required', 'email', 'max:150', 'unique:customers,email'],
            'city' => ['nullable', 'string', 'max:80'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'terms' => ['accepted'],
        ], [
            'phone.regex' => 'Escribe un número de celular válido (solo números).',
            'terms.accepted' => 'Debes aceptar el uso de tus datos para contactarte.',
        ], ['name' => 'nombre', 'phone' => 'celular', 'email' => 'correo', 'password' => 'contraseña']);

        $data['phone'] = preg_replace('/\D+/', '', $data['phone']);
        unset($data['terms']);

        // Si el equipo ya lo había registrado en una venta (mismo celular) se reutiliza la ficha
        $customer = Customer::whereNull('password')->where('phone', $data['phone'])->first();
        if ($customer) {
            $customer->update($data);
        } else {
            $customer = Customer::create($data);
        }

        $customer->update(['last_login_at' => now()]);
        $customer->adoptVisitorLikes($request->attributes->get('visitor_token'));

        Auth::guard('customer')->login($customer, true);
        $request->session()->regenerate();

        return redirect()->intended(route('customer.account'))->with('status', "¡Bienvenido, {$customer->firstName()}! Tu cuenta está lista.");
    }

    public function showLogin()
    {
        return view('customer.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
        ], [], ['login' => 'correo o celular', 'password' => 'contraseña']);

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $value = $field === 'phone' ? preg_replace('/\D+/', '', $data['login']) : $data['login'];

        if (! Auth::guard('customer')->attempt([$field => $value, 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            return back()->withInput($request->only('login'))
                ->withErrors(['login' => 'Datos incorrectos. Revisa tu correo/celular y contraseña.']);
        }

        $request->session()->regenerate();
        $customer = Auth::guard('customer')->user();
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
}
