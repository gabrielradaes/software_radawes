@extends('admin.layouts.app')
@section('title', 'Stock · '.$branch->name)
@section('actions')
    @unless($scoped)<a href="{{ route('admin.stock.global') }}" class="bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">🌐 Stock global (todas las tiendas)</a>@endunless
@endsection

@section('content')
<div class="grid grid-cols-3 gap-4 mb-5 max-w-xl">
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Unidades</p><p class="text-2xl font-bold">{{ $totals['unidades'] }}</p></div>
    <div class="bg-white rounded-xl border border-amber-200 p-4"><p class="text-xs text-amber-700">Stock bajo</p><p class="text-2xl font-bold text-amber-700">{{ $totals['bajo'] }}</p></div>
    <div class="bg-white rounded-xl border border-rose-200 p-4"><p class="text-xs text-rose-700">Agotados</p><p class="text-2xl font-bold text-rose-700">{{ $totals['agotado'] }}</p></div>
</div>

@can('stock.adjust')
<div class="bg-sky-50 border border-sky-200 text-sky-900 text-sm rounded-xl px-4 py-3 mb-5">
    <strong>Cómo cargar stock:</strong> usa <em>Grilla</em> en cualquier fila para cargar todas las tallas y colores de ese producto de una vez, o <em>Movimiento</em> para una sola variante (entrada, salida o ajuste). Todo queda en el kardex.
</div>
@endcan
<form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-5 flex flex-wrap gap-3 items-center">
    @unless($scoped)
        <select name="sucursal" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach($branches as $b)<option value="{{ $b->id }}" @selected($b->id === $branch->id)>{{ $b->name }}</option>@endforeach
        </select>
    @endunless
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Producto o SKU…" class="flex-1 min-w-[180px] rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <select name="categoria" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Todas las categorías</option>
        @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('categoria') == $c->id)>{{ $c->parent_id ? '— ' : '' }}{{ $c->name }}</option>@endforeach
    </select>
    <label class="text-sm flex items-center gap-1.5"><input type="checkbox" name="bajo" value="1" @checked(request('bajo')) class="rounded"> Solo stock bajo</label>
    <label class="text-sm flex items-center gap-1.5"><input type="checkbox" name="con_stock" value="1" @checked(request('con_stock')) class="rounded"> Solo con stock</label>
    <button class="bg-slate-800 text-white text-sm px-4 py-2 rounded-lg">Filtrar</button>
</form>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-4 py-3">Producto</th><th class="px-4 py-3">Variante</th><th class="px-4 py-3">SKU</th><th class="px-4 py-3 text-right">Precio</th><th class="px-4 py-3 text-center">Stock</th><th class="px-4 py-3 text-center">Mín.</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($stocks as $s)
                @php $v = $s->variant; $p = $v->product; @endphp
                <tr class="hover:bg-slate-50 {{ $s->quantity == 0 ? 'bg-rose-50/40' : ($s->isLow() ? 'bg-amber-50/50' : '') }}" x-data="{ open: false }">
                    <td class="px-4 py-2.5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded bg-slate-100 overflow-hidden shrink-0">@if($p->primaryImage)<img src="{{ $p->primaryImage->url() }}" class="w-full h-full object-cover">@endif</div>
                            <div><p class="font-medium leading-tight">{{ $p->name }}</p><p class="text-xs text-slate-400">{{ $p->category->name }}</p></div>
                        </div>
                    </td>
                    <td class="px-4 py-2.5">{{ $v->label() ?: '—' }}</td>
                    <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ $v->sku }}</td>
                    <td class="px-4 py-2.5 text-right">{{ money($v->effectivePrice()) }}</td>
                    <td class="px-4 py-2.5 text-center"><span class="text-lg font-bold {{ $s->quantity == 0 ? 'text-rose-600' : ($s->isLow() ? 'text-amber-600' : 'text-emerald-700') }}">{{ $s->quantity }}</span></td>
                    <td class="px-4 py-2.5 text-center text-slate-500">{{ $s->min_quantity }}</td>
                    <td class="px-4 py-2.5 text-right whitespace-nowrap text-xs">
                        <a href="{{ route('admin.stock.kardex', $s) }}" class="text-slate-500 hover:underline">Kardex</a>
                        @can('stock.adjust')
                            <a href="{{ route('admin.productos.variantes', ['product' => $p->id, 'sucursal' => $branch->id]) }}" class="ml-3 text-slate-500 hover:underline" title="Cargar todas las tallas de este producto">Grilla</a>
                            <button @click="open = !open" class="ml-3 font-semibold text-brand-500 hover:underline">Movimiento</button>
                            <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-8 mt-1 z-10 bg-white border border-slate-200 rounded-xl shadow-xl p-4 w-72 text-left">
                                <form method="POST" action="{{ route('admin.stock.move', $s) }}" class="space-y-2 text-sm">
                                    @csrf
                                    <p class="font-semibold">{{ $p->name }} · {{ $v->label() }}</p>
                                    <select name="type" class="w-full rounded-lg border border-slate-300 px-2 py-1.5">
                                        <option value="entrada">Entrada (+)</option>
                                        <option value="salida">Salida (−)</option>
                                        <option value="ajuste">Ajuste (fijar cantidad)</option>
                                    </select>
                                    <input type="number" name="quantity" min="1" required placeholder="Cantidad" class="w-full rounded-lg border border-slate-300 px-2 py-1.5">
                                    <input name="reason" maxlength="255" placeholder="Motivo (ej. llegó pedido)" class="w-full rounded-lg border border-slate-300 px-2 py-1.5">
                                    <button class="w-full bg-brand-900 text-white rounded-lg py-1.5 font-semibold">Registrar</button>
                                </form>
                            </div>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Sin resultados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $stocks->links() }}</div>
@endsection
