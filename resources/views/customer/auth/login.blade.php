@extends('layouts.app')
@section('title', 'Iniciar sesión · '.$currentBranch->name)

@section('content')
<div class="max-w-md mx-auto px-4 py-14">
    <div class="text-center mb-8">
        <span class="inline-flex w-14 h-14 rounded-full bg-brand-900 text-gold-400 font-display font-bold text-xl items-center justify-center ring-2 ring-gold-500/40">RW</span>
        <h1 class="font-display text-3xl font-bold mt-4">Inicia sesión</h1>
        <p class="text-brand-700 text-sm mt-1">Con tu Gmail o tu celular</p>
    </div>
    <form method="POST" action="{{ route('customer.login') }}" class="bg-white rounded-3xl border border-brand-100 shadow-sm p-7 space-y-4">
        @csrf
        @error('login')<p class="bg-rose-50 text-rose-700 text-sm px-3 py-2 rounded-xl">{{ $message }}</p>@enderror
        <div>
            <label class="block text-sm font-medium mb-1">Gmail o celular</label>
            <input name="login" value="{{ old('login') }}" required autofocus class="w-full rounded-xl border border-brand-200 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-300">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Contraseña</label>
            <input type="password" name="password" required class="w-full rounded-xl border border-brand-200 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-300">
        </div>
        <label class="flex items-center gap-2 text-sm text-brand-700"><input type="checkbox" name="remember" checked class="rounded"> Mantener sesión iniciada</label>
        <button class="w-full bg-brand-900 hover:bg-brand-800 text-white font-semibold py-3 rounded-full transition">Entrar</button>
    </form>
    <p class="text-center text-sm text-brand-500 mt-6">¿No tienes cuenta? <a href="{{ route('customer.register') }}" class="font-semibold text-brand-900 hover:underline">Regístrate gratis</a></p>
    <p class="text-center text-sm mt-3"><a href="{{ route('customer.password.forgot') }}" class="font-semibold text-brand-900 hover:underline">¿Olvidaste tu contraseña?</a></p>
</div>
@endsection
