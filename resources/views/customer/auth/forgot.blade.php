@extends('layouts.app')
@section('title', 'Recuperar contraseña · '.$currentBranch->brandName())

@section('content')
<div class="max-w-md mx-auto px-4 py-14">
    <div class="text-center mb-8">
        <span class="inline-flex w-16 h-16 rounded-full bg-brand-100 text-3xl items-center justify-center">🔑</span>
        <h1 class="font-display text-3xl font-bold mt-4">¿Olvidaste tu contraseña?</h1>
        <p class="text-brand-700 text-sm mt-2">Escribe el Gmail de tu cuenta y te enviaremos un código para crear una nueva.</p>
    </div>
    <form method="POST" action="{{ route('customer.password.forgot') }}" class="bg-white rounded-3xl border border-brand-100 shadow-sm p-7 space-y-4">
        @csrf
        @error('email')<p class="bg-rose-50 text-rose-700 text-sm px-3 py-2 rounded-xl">{{ $message }}</p>@enderror
        <div>
            <label class="block text-sm font-medium mb-1">Tu Gmail</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="tunombre@gmail.com"
                   pattern="[^@\s]+@[gG][mM][aA][iI][lL]\.[cC][oO][mM]" title="Debe terminar en @gmail.com"
                   class="w-full rounded-xl border border-brand-200 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-300">
        </div>
        <button class="w-full bg-brand-900 hover:bg-brand-800 text-white font-semibold py-3 rounded-full transition">Enviarme el código</button>
    </form>
    <p class="text-center text-sm text-brand-500 mt-6"><a href="{{ route('customer.login') }}" class="font-semibold text-brand-900 hover:underline">← Volver a iniciar sesión</a></p>
</div>
@endsection
