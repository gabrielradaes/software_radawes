@extends('admin.layouts.app')
@section('title', 'Nuevo cliente')
@section('actions')<a href="{{ route('admin.clientes.index') }}" class="text-sm text-slate-600 hover:underline">← Clientes</a>@endsection

@section('content')
<form method="POST" action="{{ route('admin.clientes.store') }}" class="bg-white rounded-xl border border-slate-200 p-6 max-w-xl space-y-4">
    @csrf
    <x-admin.field label="Nombre completo" name="name"><input name="name" value="{{ old('name') }}" required autofocus class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
    <div class="grid sm:grid-cols-2 gap-4">
        <x-admin.field label="Celular (WhatsApp)" name="phone"><input name="phone" value="{{ old('phone') }}" required inputmode="numeric" placeholder="77712345" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
        <x-admin.field label="Ciudad" name="city"><input name="city" value="{{ old('city', $customer->city) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
    </div>
    <x-admin.field label="Gmail (opcional)" name="email" help="Si lo pones, el cliente puede activar su cuenta en la página con «¿Olvidaste tu contraseña?» y le llega un código.">
        <input type="email" name="email" value="{{ old('email') }}" placeholder="cliente@gmail.com" pattern="[^@\s]+@[gG][mM][aA][iI][lL]\.[cC][oO][mM]" title="Debe terminar en @gmail.com" class="w-full rounded-lg border border-slate-300 px-3 py-2">
    </x-admin.field>
    <x-admin.field label="CI / NIT (para facturas)" name="document"><input name="document" value="{{ old('document') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
    <x-admin.field label="Notas internas" name="notes"><textarea name="notes" rows="3" placeholder="Talla habitual, preferencias…" class="w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('notes') }}</textarea></x-admin.field>
    <div class="flex gap-3 pt-2">
        <button class="bg-brand-900 hover:bg-brand-700 text-white font-semibold px-5 py-2.5 rounded-lg">Registrar cliente</button>
        <a href="{{ route('admin.clientes.index') }}" class="px-5 py-2.5 text-sm text-slate-500 hover:underline">Cancelar</a>
    </div>
</form>
@endsection
