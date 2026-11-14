@extends('layouts.app')
@section('title', 'Nueva contraseña · '.$currentBranch->brandName())

@section('content')
<div class="max-w-md mx-auto px-4 py-14">
    <div class="text-center mb-8">
        <span class="inline-flex w-16 h-16 rounded-full bg-brand-100 text-3xl items-center justify-center">📩</span>
        <h1 class="font-display text-3xl font-bold mt-4">Crea tu nueva contraseña</h1>
        <p class="text-brand-700 text-sm mt-2">Escribe el código que enviamos a <strong class="text-brand-900">{{ $email }}</strong></p>
        <p class="text-xs text-brand-500 mt-2">¿No llegó? Revisa <strong>Spam</strong> o <a href="{{ route('customer.password.forgot') }}" class="font-semibold text-brand-900 hover:underline">pide otro código</a>.</p>
    </div>
    <form method="POST" action="{{ route('customer.password.reset') }}" class="bg-white rounded-3xl border border-brand-100 shadow-sm p-7 space-y-4" x-data="{ code: '' }">
        @csrf
        @if($errors->any())<p class="bg-rose-50 text-rose-700 text-sm px-3 py-2 rounded-xl">{{ $errors->first() }}</p>@endif
        <div>
            <label class="block text-sm font-medium mb-1">Código de 6 dígitos</label>
            <input name="code" x-model="code" @input="code = code.replace(/\D/g, '').slice(0, 6)" required autofocus
                   inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="••••••"
                   class="w-full text-center text-2xl font-bold tracking-[0.5em] rounded-xl border border-brand-200 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-300">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Nueva contraseña</label>
            <input type="password" name="password" required minlength="6" autocomplete="new-password" class="w-full rounded-xl border border-brand-200 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-300">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Repetir nueva contraseña</label>
            <input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-xl border border-brand-200 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-300">
        </div>
        <button class="w-full bg-brand-900 hover:bg-brand-800 text-white font-semibold py-3 rounded-full transition">Cambiar contraseña</button>
    </form>
</div>
@endsection
