@extends('admin.layouts.app')
@section('title', 'Variantes y stock: '.$product->name)
@section('actions')
    <a href="{{ route('admin.etiquetas', ['producto' => $product->id, 'sucursal' => $branch->id]) }}" target="_blank" class="bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold px-3 py-2 rounded-lg" title="Una etiqueta por cada par en stock de esta tienda">🏷️ Etiquetas</a>
    @can('products.manage')<a href="{{ route('admin.productos.edit', $product) }}" class="text-sm text-slate-600 hover:underline">← Editar producto</a>@else<a href="{{ route('admin.stock.index') }}" class="text-sm text-slate-600 hover:underline">← Volver a stock</a>@endcan
@endsection

@section('content')
@php
    $colorAttr = $attributes->firstWhere('slug', 'color');
    $tallaAttr = $attributes->firstWhere('slug', 'talla');
@endphp
<div class="grid lg:grid-cols-3 gap-6">
    {{-- Generar --}}
    <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
        <h2 class="font-semibold">Generar variantes</h2>
        <p class="text-xs text-slate-500">Marca colores y tallas: se crean todas las combinaciones que falten (con stock 0 en cada sucursal).</p>
        <form method="POST" action="{{ route('admin.productos.variantes.generar', $product) }}" class="space-y-4">
            @csrf
            <div>
                <p class="text-sm font-medium mb-2">Colores</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($colorAttr?->values ?? [] as $v)
                        <label class="inline-flex items-center gap-1.5 text-sm border border-slate-200 rounded-full px-2.5 py-1 cursor-pointer has-[:checked]:bg-brand-900 has-[:checked]:text-white">
                            <input type="checkbox" name="colores[]" value="{{ $v->id }}" class="hidden">
                            <span class="w-3 h-3 rounded-full border border-white/50" style="background: {{ $v->meta ?? '#999' }}"></span>{{ $v->value }}
                        </label>
                    @endforeach
                </div>
                <div class="flex gap-2 mt-2">
                    <input name="new_color" placeholder="Color nuevo" class="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                    <input type="color" name="new_color_hex" value="#8a5a3c" class="w-10 h-9 rounded border border-slate-300">
                </div>
            </div>
            <div>
                <p class="text-sm font-medium mb-2">Tallas</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($tallaAttr?->values ?? [] as $v)
                        <label class="inline-flex items-center text-sm border border-slate-200 rounded-lg w-11 h-9 justify-center cursor-pointer has-[:checked]:bg-brand-900 has-[:checked]:text-white">
                            <input type="checkbox" name="tallas[]" value="{{ $v->id }}" class="hidden">{{ $v->value }}
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-slate-400 mt-1">Para productos sin talla (motos, accesorios) marca solo colores.</p>
            </div>
            <button class="w-full bg-brand-900 hover:bg-brand-700 text-white font-semibold py-2.5 rounded-lg text-sm">Generar</button>
        </form>
    </div>

    {{-- Grilla --}}
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center gap-3">
            <h2 class="font-semibold">Stock en</h2>
            <form method="GET" class="flex items-center gap-2">
                <select name="sucursal" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                    @foreach($branches as $b)<option value="{{ $b->id }}" @selected($b->id === $branch->id)>{{ $b->name }}</option>@endforeach
                </select>
            </form>
            <span class="ml-auto text-sm text-slate-500">{{ $rows->count() }} variantes · {{ $rows->sum('qty') }} unidades</span>
        </div>
        @if($rows->count())
            <form method="POST" action="{{ route('admin.productos.variantes.stock', $product) }}">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left">
                        <tr><th class="px-4 py-2">SKU</th><th class="px-4 py-2">Color</th><th class="px-4 py-2">Talla</th><th class="px-4 py-2">Precio esp.</th><th class="px-4 py-2 text-center">Stock</th><th class="px-4 py-2 text-center">Mínimo</th><th class="px-4 py-2"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($rows as $r)
                            @php $v = $r['variant']; @endphp
                            <tr class="{{ $v->is_active ? '' : 'opacity-50' }} {{ $r['qty'] <= $r['min'] ? 'bg-amber-50/60' : '' }}">
                                <td class="px-4 py-2 font-mono text-xs">{{ $v->sku }}</td>
                                <td class="px-4 py-2">{{ $r['color'] }}</td>
                                <td class="px-4 py-2">{{ $r['talla'] }}</td>
                                <td class="px-4 py-2"><input type="number" step="0.01" min="0" name="price[{{ $v->id }}]" value="{{ $v->price }}" placeholder="{{ $product->base_price }}" class="w-24 rounded border border-slate-300 px-2 py-1 text-sm"></td>
                                <td class="px-4 py-2 text-center"><input type="number" min="0" name="qty[{{ $v->id }}]" value="{{ $r['qty'] }}" class="w-20 rounded border border-slate-300 px-2 py-1 text-sm text-center font-semibold {{ $r['qty'] == 0 ? 'text-rose-600' : '' }}"></td>
                                <td class="px-4 py-2 text-center"><input type="number" min="0" name="min[{{ $v->id }}]" value="{{ $r['min'] }}" class="w-16 rounded border border-slate-300 px-2 py-1 text-sm text-center"></td>
                                <td class="px-4 py-2 text-right whitespace-nowrap text-xs">
                                    @if($r['stock'])<a href="{{ route('admin.stock.kardex', $r['stock']) }}" class="text-slate-500 hover:underline">Kardex</a>@endif
                                    @can('products.manage')
                                    <button type="submit" form="v-toggle-{{ $v->id }}" class="ml-2 text-slate-500 hover:underline">{{ $v->is_active ? 'Desactivar' : 'Activar' }}</button>
                                    <button type="submit" form="v-del-{{ $v->id }}" class="ml-2 text-rose-600 hover:underline">Quitar</button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
                    <p class="text-xs text-slate-500">Al guardar, cada cambio de cantidad queda registrado en el kardex como ajuste.</p>
                    <button class="bg-brand-900 hover:bg-brand-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">Guardar stock</button>
                </div>
            </form>
            @foreach($rows as $r)
                <form id="v-toggle-{{ $r['variant']->id }}" method="POST" action="{{ route('admin.variantes.update', $r['variant']) }}">@csrf @method('PATCH')</form>
                <form id="v-del-{{ $r['variant']->id }}" method="POST" action="{{ route('admin.variantes.destroy', $r['variant']) }}" onsubmit="return confirmar('¿Quitar la variante {{ $r['variant']->sku }}?')">@csrf @method('DELETE')</form>
            @endforeach
        @else
            <p class="px-5 py-10 text-center text-slate-400 text-sm">Este producto aún no tiene variantes. Genera las combinaciones a la izquierda.</p>
        @endif
    </div>
</div>
@endsection
