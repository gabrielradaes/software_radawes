@extends('admin.layouts.app')
@section('title', 'Stock global · todas las tiendas')
@section('actions')<a href="{{ route('admin.stock.index') }}" class="text-sm text-slate-600 hover:underline">← Stock por tienda</a>@endsection

@section('content')
<div class="grid gap-4 mb-5" style="grid-template-columns: repeat({{ $branches->count() }}, minmax(0, 1fr)); max-width: {{ 220 * $branches->count() }}px">
    @foreach($branches as $b)
        <div class="bg-white rounded-xl border border-slate-200 p-4 border-t-4" style="border-top-color: {{ $b->color_primary }}">
            <p class="text-xs text-slate-500">{{ $b->brandName() }} · {{ $b->city }}</p>
            <p class="text-2xl font-bold">{{ $totals[$b->id] }} <span class="text-sm font-normal text-slate-400">unidades</span></p>
            <a href="{{ route('admin.stock.index', ['sucursal' => $b->id]) }}" class="text-xs text-brand-500 hover:underline">Ver detalle →</a>
        </div>
    @endforeach
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-5 flex flex-wrap gap-3">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Producto…" class="flex-1 min-w-[180px] rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <select name="categoria" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Todas las categorías</option>
        @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('categoria') == $c->id)>{{ $c->parent_id ? '— ' : '' }}{{ $c->name }}</option>@endforeach
    </select>
    <button class="bg-slate-800 text-white text-sm px-4 py-2 rounded-lg">Filtrar</button>
</form>

<div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left">
            <tr>
                <th class="px-4 py-3">Producto</th>
                <th class="px-4 py-3">Variante</th>
                @foreach($branches as $b)<th class="px-4 py-3 text-center" style="border-bottom: 3px solid {{ $b->color_primary }}">{{ $b->brandName() }}<br><span class="normal-case font-normal">{{ $b->city }}</span></th>@endforeach
                <th class="px-4 py-3 text-center">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($variants as $v)
                @php $p = $v->product; $sold = $p->branches->pluck('id'); $sum = 0; @endphp
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded bg-slate-100 overflow-hidden shrink-0">@if($p->primaryImage)<img src="{{ $p->primaryImage->url() }}" class="w-full h-full object-cover">@endif</div>
                            <div><p class="font-medium leading-tight">{{ $p->name }}</p><p class="text-xs text-slate-400">{{ $p->category->name }}</p></div>
                        </div>
                    </td>
                    <td class="px-4 py-2">{{ $v->label() ?: '—' }} <span class="block font-mono text-[11px] text-slate-400">{{ $v->sku }}</span></td>
                    @foreach($branches as $b)
                        @php $s = $v->stocks->firstWhere('branch_id', $b->id); $q = (int) ($s->quantity ?? 0); $sum += $q; @endphp
                        <td class="px-4 py-2 text-center">
                            @if(! $sold->contains($b->id))
                                <span class="text-slate-300" title="No se vende en esta tienda">—</span>
                            @else
                                <a href="{{ $s ? route('admin.stock.kardex', $s) : '#' }}" class="font-bold {{ $q == 0 ? 'text-rose-500' : ($s && $s->isLow() ? 'text-amber-600' : 'text-emerald-700') }}">{{ $q }}</a>
                            @endif
                        </td>
                    @endforeach
                    <td class="px-4 py-2 text-center font-bold">{{ $sum }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 3 + $branches->count() }}" class="px-4 py-10 text-center text-slate-400">Sin resultados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $variants->links() }}</div>
@endsection
