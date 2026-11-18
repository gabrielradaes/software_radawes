@extends('admin.layouts.app')
@section('title', 'Proveedores')
@section('actions')<a href="{{ route('admin.compras.index') }}" class="text-sm text-slate-600 hover:underline">← Compras</a>@endsection

@section('content')
<div class="grid lg:grid-cols-3 gap-6">
    <form method="POST" action="{{ route('admin.proveedores.store') }}" class="bg-white rounded-xl border border-slate-200 p-5 space-y-3 h-fit">
        @csrf
        <h3 class="font-semibold">Nuevo proveedor</h3>
        <x-admin.field label="Nombre / empresa" name="name"><input name="name" required value="{{ old('name') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
        <x-admin.field label="Contacto" name="contact"><input name="contact" value="{{ old('contact') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
        <div class="grid grid-cols-2 gap-3">
            <x-admin.field label="Celular" name="phone"><input name="phone" value="{{ old('phone') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
            <x-admin.field label="Ciudad" name="city"><input name="city" value="{{ old('city') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
        </div>
        <x-admin.field label="País" name="country"><input name="country" value="{{ old('country', 'Brasil') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
        <x-admin.field label="Notas" name="notes"><textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea></x-admin.field>
        <button class="bg-brand-900 text-white text-sm font-semibold px-4 py-2 rounded-lg">Agregar</button>
    </form>
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
        @forelse($suppliers as $s)
            <details class="p-4 {{ $s->is_active ? '' : 'opacity-60' }}">
                <summary class="cursor-pointer flex flex-wrap justify-between gap-2">
                    <span><strong>{{ $s->name }}</strong> <span class="text-xs text-slate-500">{{ $s->contact }} {{ $s->phone ? '· '.$s->phone : '' }} {{ $s->city ? '· '.$s->city : '' }}</span></span>
                    <span class="text-sm text-slate-600">{{ $s->purchases_count }} ingresos · {{ money($s->purchases_sum_total ?? 0) }}</span>
                </summary>
                <form method="POST" action="{{ route('admin.proveedores.update', $s) }}" class="mt-3 grid sm:grid-cols-3 gap-2">
                    @csrf @method('PUT')
                    <input name="name" value="{{ $s->name }}" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="contact" value="{{ $s->contact }}" placeholder="Contacto" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="phone" value="{{ $s->phone }}" placeholder="Celular" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="city" value="{{ $s->city }}" placeholder="Ciudad" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="country" value="{{ $s->country }}" placeholder="País" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <label class="text-sm flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($s->is_active) class="rounded"> Activo</label>
                    <textarea name="notes" rows="2" placeholder="Notas" class="sm:col-span-3 rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $s->notes }}</textarea>
                    <div class="sm:col-span-3 flex gap-3 items-center">
                        <button class="bg-slate-800 text-white text-sm font-semibold px-4 py-2 rounded-lg">Guardar</button>
                        <a href="{{ route('admin.compras.index', ['proveedor' => $s->id]) }}" class="text-sm text-brand-700 hover:underline">Ver sus ingresos</a>
                    </div>
                </form>
            </details>
        @empty
            <p class="p-6 text-sm text-slate-400">Aún no hay proveedores.</p>
        @endforelse
    </div>
</div>
@endsection
