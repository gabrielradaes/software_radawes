@extends('admin.layouts.app')
@section('title', 'Productos')
@section('actions')
    <a href="{{ route('admin.productos.create') }}" class="bg-brand-900 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ Nuevo producto</a>
@endsection

@section('content')
<form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-5 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-[200px]">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre o material…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <select name="categoria" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Todas las categorías</option>
        @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('categoria') == $c->id)>{{ $c->parent_id ? '— ' : '' }}{{ $c->name }}</option>@endforeach
    </select>
    @if($branches->count())
    <select name="sucursal" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Todas las tiendas</option>
        @foreach($branches as $b)<option value="{{ $b->id }}" @selected(request("sucursal") == $b->id)>{{ $b->name }}</option>@endforeach
    </select>
    @endif
    <select name="estado" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Todos</option>
        <option value="borradores" @selected(request('estado') === 'borradores')>Borradores</option>
        <option value="inactivos" @selected(request('estado') === 'inactivos')>Inactivos</option>
    </select>
    <button class="bg-slate-800 text-white text-sm px-4 py-2 rounded-lg">Filtrar</button>
</form>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Producto</th>
                <th class="px-4 py-3">Categoría</th>
                <th class="px-4 py-3">Tiendas</th>
                <th class="px-4 py-3 text-right">Precio</th>
                <th class="px-4 py-3 text-center">Variantes</th>
                <th class="px-4 py-3 text-center">Stock</th>
                <th class="px-4 py-3 text-center">♥ / WA</th>
                <th class="px-4 py-3">Estado</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($products as $p)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-lg bg-slate-100 overflow-hidden shrink-0">@if($p->primaryImage)<img src="{{ $p->primaryImage->url() }}" class="w-full h-full object-cover">@endif</div>
                            <div class="min-w-0">
                                <a href="{{ route('admin.productos.edit', $p) }}" class="font-semibold hover:text-brand-500">{{ $p->name }}</a>
                                <p class="text-xs text-slate-400">{{ $p->brand?->name }} · <a href="{{ route('products.show', ['branch' => $p->branches->first()?->slug ?? $currentBranch->slug, 'product' => $p]) }}" target="_blank" class="hover:underline">ver en sitio ↗</a></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ $p->category->name }}</td>
                    <td class="px-4 py-3"><div class="flex flex-wrap gap-1">@foreach($p->branches as $b)<span class="text-[10px] font-semibold px-1.5 py-0.5 rounded text-white" style="background: {{ $b->color_primary }}" title="{{ $b->name }}">{{ $b->brandName() }} {{ $b->city }}</span>@endforeach</div></td>
                    <td class="px-4 py-3 text-right font-medium">{{ money($p->base_price) }} @if($p->hasDiscount())<span class="block text-xs text-slate-400 line-through">{{ money($p->compare_at_price) }}</span>@endif</td>
                    <td class="px-4 py-3 text-center">{{ $p->variants_count }}</td>
                    <td class="px-4 py-3 text-center"><span class="font-semibold {{ ($stockTotals[$p->id] ?? 0) == 0 ? 'text-rose-600' : '' }}">{{ $stockTotals[$p->id] ?? 0 }}</span></td>
                    <td class="px-4 py-3 text-center text-slate-600">{{ $p->likes_count }} / {{ $p->whatsapp_leads_count }}</td>
                    <td class="px-4 py-3">
                        @if(! $p->is_active)<span class="text-xs px-2 py-0.5 rounded-full bg-slate-200">Inactivo</span>
                        @elseif(! $p->published_at)<span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Borrador</span>
                        @else<span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">Publicado</span>@endif
                        @if($p->is_featured)<span class="text-xs px-2 py-0.5 rounded-full bg-gold-400/30 text-brand-800 ml-1">★</span>@endif
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('admin.productos.variantes', $p) }}" class="text-brand-500 hover:underline text-xs font-semibold">Variantes/stock</a>
                        <a href="{{ route('admin.productos.edit', $p) }}" class="ml-3 text-slate-600 hover:underline text-xs">Editar</a>
                        @can('records.delete')<form method="POST" action="{{ route('admin.productos.destroy', $p) }}" class="inline ml-3" onsubmit="return confirmar('¿Eliminar {{ $p->name }}?')">@csrf @method('DELETE')<button class="text-rose-600 hover:underline text-xs">Eliminar</button></form>@endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-4 py-10 text-center text-slate-400">No hay productos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
