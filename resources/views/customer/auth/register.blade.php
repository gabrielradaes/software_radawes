@extends('layouts.app')
@section('title', 'Crear cuenta · '.$currentBranch->name)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-12 grid md:grid-cols-2 gap-10 items-start">
    <div class="md:sticky md:top-24">
        <p class="text-xs uppercase tracking-[0.2em] text-brand-500 font-semibold">Únete a RadaWes</p>
        <h1 class="font-display text-4xl font-bold mt-2">Crea tu cuenta</h1>
        <p class="text-brand-700 mt-3">Es gratis y te toma un minuto. Con tu cuenta puedes:</p>
        <ul class="mt-4 space-y-2 text-sm text-brand-800">
            <li class="flex gap-2"><span>💬</span> Comentar y preguntar en productos y novedades.</li>
            <li class="flex gap-2"><span>❤️</span> Guardar tus favoritos y verlos desde cualquier dispositivo.</li>
            <li class="flex gap-2"><span>📱</span> Que te respondamos por WhatsApp sabiendo quién eres.</li>
            <li class="flex gap-2"><span>🧾</span> Ver el historial de tus compras en tienda.</li>
        </ul>
        <p class="mt-6 text-sm text-brand-500">¿Ya tienes cuenta? <a href="{{ route('customer.login') }}" class="font-semibold text-brand-900 hover:underline">Inicia sesión</a></p>
    </div>

    <form method="POST" action="{{ route('customer.register') }}" class="bg-white rounded-3xl border border-brand-100 shadow-sm p-7 space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Nombre completo</label>
            <input name="name" value="{{ old('name') }}" required autofocus class="w-full rounded-xl border border-brand-200 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-300">
            @error('name')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Celular (WhatsApp)</label>
                <input name="phone" value="{{ old('phone') }}" required inputmode="numeric" placeholder="77712345" class="w-full rounded-xl border border-brand-200 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-300">
                @error('phone')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Ciudad</label>
                <input name="city" value="{{ old('city', 'La Paz') }}" class="w-full rounded-xl border border-brand-200 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-300">
            </div>
        </div>
        <div x-data="{ v: @js(old('email', '')) }">
            <label class="block text-sm font-medium mb-1">Gmail</label>
            <input type="email" name="email" x-model="v" required placeholder="tunombre@gmail.com"
                   pattern="[^@\s]+@[gG][mM][aA][iI][lL]\.[cC][oO][mM]" title="Debe terminar en @gmail.com"
                   class="w-full rounded-xl border border-brand-200 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-300">
            <p class="text-xs mt-1" :class="v && !/@gmail\.com$/i.test(v.trim()) ? 'text-rose-600' : 'text-brand-500'">
                Solo cuentas <strong>@gmail.com</strong>. Te enviaremos un código para confirmar que es tuyo.
            </p>
            @error('email')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Contraseña</label>
                <input type="password" name="password" required minlength="6" class="w-full rounded-xl border border-brand-200 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-300">
                @error('password')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Repetir contraseña</label>
                <input type="password" name="password_confirmation" required class="w-full rounded-xl border border-brand-200 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-300">
            </div>
        </div>
        <label class="flex items-start gap-2 text-xs text-brand-700">
            <input type="checkbox" name="terms" value="1" @checked(old('terms')) class="mt-0.5 rounded">
            <span>Acepto que {{ $currentBranch->name }} use mis datos para responder mis consultas y avisarme de novedades. No los compartimos con terceros.</span>
        </label>
        @error('terms')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
        <button class="w-full bg-brand-900 hover:bg-brand-800 text-white font-semibold py-3 rounded-full transition">Crear cuenta</button>
    </form>
</div>
@endsection
