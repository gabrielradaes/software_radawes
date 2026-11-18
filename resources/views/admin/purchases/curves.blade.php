@extends('admin.layouts.app')
@section('title', 'Curvas de tallas')
@section('actions')<a href="{{ route('admin.compras.index') }}" class="text-sm text-slate-600 hover:underline">← Compras</a>@endsection

@section('content')
<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100 h-fit">
        @forelse($curves as $c)
            <div class="p-4 flex items-center gap-3">
                <div class="flex-1">
                    <p class="font-semibold">{{ $c->name }} <span class="text-xs text-slate-500 font-normal">({{ $c->pairs() }} pares por color)</span></p>
                    <div class="flex flex-wrap gap-1 mt-1.5">
                        @foreach($c->items as $i)<span class="text-center border border-slate-200 rounded px-2 py-0.5 text-xs"><span class="text-slate-500">{{ $i->size }}</span> <strong>{{ $i->quantity }}</strong></span>@endforeach
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.curvas.destroy', $c) }}" onsubmit="return confirm('¿Eliminar la curva {{ $c->name }}?')">@csrf @method('DELETE')<button class="text-xs text-rose-600 hover:underline">Eliminar</button></form>
            </div>
        @empty
            <p class="p-6 text-sm text-slate-400">No hay curvas.</p>
        @endforelse
    </div>
    <form method="POST" action="{{ route('admin.curvas.store') }}" class="bg-white rounded-xl border border-slate-200 p-5 space-y-4 h-fit">
        @csrf
        <h3 class="font-semibold">Nueva curva</h3>
        <p class="text-sm text-slate-500">Cuántos pares de cada talla vienen <strong>por color</strong> cuando pides ese modelo al proveedor.</p>
        <x-admin.field label="Nombre" name="name"><input name="name" required value="{{ old('name') }}" placeholder="Ej.: Caballero 39-44" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
        @error('sizes')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
        <div class="flex flex-wrap gap-1.5">
            @foreach(range(33, 46) as $s)
                <label class="w-14 text-center text-xs text-slate-500"><span class="font-semibold">{{ $s }}</span>
                    <input type="number" min="0" max="99" name="sizes[{{ $s }}]" value="{{ old('sizes.'.$s) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-1 py-2 text-sm text-center"></label>
            @endforeach
        </div>
        <button class="bg-brand-900 text-white text-sm font-semibold px-4 py-2 rounded-lg">Crear curva</button>
    </form>
</div>
@endsection
