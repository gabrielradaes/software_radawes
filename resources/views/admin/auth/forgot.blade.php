@extends('admin.auth.layout')
@section('title', 'Recuperar contraseña')
@section('heading', 'Recuperar acceso')
@section('subheading', 'Te enviaremos un código a tu correo')

@section('form')
<form method="POST" action="{{ route('admin.password.forgot') }}" class="bg-white rounded-2xl shadow-2xl p-7 space-y-4">
    @csrf
    @error('email')<p class="bg-rose-50 text-rose-700 text-sm px-3 py-2 rounded-lg">{{ $message }}</p>@enderror
    <div>
        <label class="block text-sm font-medium mb-1">Correo con el que entras al panel</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="tunombre@gmail.com" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>
    <button class="w-full bg-brand-900 hover:bg-brand-500 text-white font-semibold py-2.5 rounded-lg transition">Enviarme el código</button>
    <p class="text-center text-sm"><a href="{{ route('admin.login') }}" class="font-semibold text-brand-900 hover:underline">← Volver a ingresar</a></p>
</form>
@endsection
