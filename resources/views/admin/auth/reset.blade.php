@extends('admin.auth.layout')
@section('title', 'Nueva contraseña')
@section('heading', 'Nueva contraseña')
@section('subheading')Código enviado a {{ $email }}@endsection

@section('form')
<form method="POST" action="{{ route('admin.password.reset') }}" class="bg-white rounded-2xl shadow-2xl p-7 space-y-4" x-data="{ code: '' }">
    @csrf
    @if($errors->any())<p class="bg-rose-50 text-rose-700 text-sm px-3 py-2 rounded-lg">{{ $errors->first() }}</p>@endif
    <p class="text-xs text-slate-500">¿No llegó? Revisa <strong>Spam</strong> o <a href="{{ route('admin.password.forgot') }}" class="font-semibold text-brand-900 hover:underline">pide otro código</a>.</p>
    <div>
        <label class="block text-sm font-medium mb-1">Código de 6 dígitos</label>
        <input name="code" x-model="code" @input="code = code.replace(/\D/g, '').slice(0, 6)" required autofocus inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="••••••"
               class="w-full text-center text-2xl font-bold tracking-[0.5em] rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Nueva contraseña <span class="text-slate-400 font-normal">(mínimo 8)</span></label>
        <input type="password" name="password" required minlength="8" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Repetir nueva contraseña</label>
        <input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>
    <button class="w-full bg-brand-900 hover:bg-brand-500 text-white font-semibold py-2.5 rounded-lg transition">Cambiar contraseña y entrar</button>
</form>
@endsection
