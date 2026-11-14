@extends('admin.auth.layout')
@section('title', 'Ingresar')

@section('form')
<form method="POST" action="{{ route('admin.login') }}" class="bg-white rounded-2xl shadow-2xl p-7 space-y-4">
    @csrf
    @if($errors->any())
        <p class="bg-rose-50 text-rose-700 text-sm px-3 py-2 rounded-lg">{{ $errors->first() }}</p>
    @endif
    <div>
        <label class="block text-sm font-medium mb-1">Correo</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Contraseña</label>
        <input type="password" name="password" required autocomplete="current-password" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" class="rounded"> Recordarme</label>
    <button class="w-full bg-brand-900 hover:bg-brand-500 text-white font-semibold py-2.5 rounded-lg transition">Ingresar</button>
    <p class="text-center text-sm"><a href="{{ route('admin.password.forgot') }}" class="font-semibold text-brand-900 hover:underline">¿Olvidaste tu contraseña?</a></p>
</form>
@endsection
