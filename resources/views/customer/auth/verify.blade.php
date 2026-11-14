@extends('layouts.app')
@section('title', 'Verifica tu Gmail · '.$currentBranch->brandName())

@section('content')
<div class="max-w-md mx-auto px-4 py-14">
    <div class="text-center mb-8">
        <span class="inline-flex w-16 h-16 rounded-full bg-brand-100 text-3xl items-center justify-center">📩</span>
        <h1 class="font-display text-3xl font-bold mt-4">Revisa tu Gmail</h1>
        <p class="text-brand-700 text-sm mt-2">Enviamos un código de <strong>6 dígitos</strong> a<br><strong class="text-brand-900">{{ $email }}</strong></p>
        <p class="text-xs text-brand-500 mt-2">Si no lo ves en unos segundos, mira en <strong>Spam</strong> o en <strong>Promociones</strong>.</p>
    </div>

    <form method="POST" action="{{ route('customer.verify') }}" class="bg-white rounded-3xl border border-brand-100 shadow-sm p-7 space-y-4"
          x-data="{ code: '' }">
        @csrf
        @error('code')<p class="bg-rose-50 text-rose-700 text-sm px-3 py-2 rounded-xl">{{ $message }}</p>@enderror
        <div>
            <label class="block text-sm font-medium mb-1 text-center">Código de verificación</label>
            <input name="code" x-model="code" @input="code = code.replace(/\D/g, '').slice(0, 6)" required autofocus
                   inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="••••••"
                   class="w-full text-center text-3xl font-bold tracking-[0.5em] rounded-xl border border-brand-200 px-3 py-3 focus:outline-none focus:ring-2 focus:ring-brand-300">
        </div>
        <button :disabled="code.length !== 6" class="w-full bg-brand-900 hover:bg-brand-800 disabled:opacity-40 text-white font-semibold py-3 rounded-full transition">Verificar</button>
    </form>

    <form method="POST" action="{{ route('customer.verify.resend') }}" class="text-center mt-5"
          x-data="{ s: 60 }" x-init="let t = setInterval(() => { if (s > 0) s--; else clearInterval(t) }, 1000)">
        @csrf
        <button :disabled="s > 0" class="text-sm font-semibold text-brand-900 hover:underline disabled:text-brand-300 disabled:no-underline">
            <span x-show="s > 0">Reenviar código en <span x-text="s"></span> s</span>
            <span x-show="s === 0" x-cloak>Reenviar código</span>
        </button>
    </form>
    <p class="text-center text-xs text-brand-500 mt-3">¿Te equivocaste de Gmail? <a href="{{ route('customer.register') }}" class="font-semibold text-brand-900 hover:underline">Vuelve a registrarte</a></p>
</div>
@endsection
