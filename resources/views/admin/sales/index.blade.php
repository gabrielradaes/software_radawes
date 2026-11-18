@extends('admin.layouts.app')
@section('title', 'Ventas')
@section('actions')@can('sales.create')<a href="{{ route('admin.ventas.create') }}" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ Nueva venta</a>@endcan @endsection

@section('content')
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Hoy</p><p class="text-xl font-bold">{{ money($totals['hoy']) }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Esta semana</p><p class="text-xl font-bold">{{ money($totals['semana']) }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Este mes</p><p class="text-xl font-bold text-emerald-700">{{ money($totals['mes']) }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Pares vendidos (mes)</p><p class="text-xl font-bold">{{ $totals['pares_mes'] }}</p></div>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-5 flex flex-wrap gap-3 items-end">
    @unless($scoped)
        <select name="sucursal" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Todas las tiendas</option>
            @foreach($branches as $b)<option value="{{ $b->id }}" @selected($branchId === $b->id)>{{ $b->name }}</option>@endforeach
        </select>
    @endunless
    <label class="text-xs text-slate-500">Desde<input type="date" name="desde" value="{{ $from->format('Y-m-d') }}" class="block rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
    <label class="text-xs text-slate-500">Hasta<input type="date" name="hasta" value="{{ $to->format('Y-m-d') }}" class="block rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Código, cliente o celular…" class="flex-1 min-w-[180px] rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <button class="bg-slate-800 text-white text-sm px-4 py-2 rounded-lg">Filtrar</button>
</form>

<div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-4 py-3">Venta</th><th class="px-4 py-3">Cliente</th><th class="px-4 py-3">Tienda · vendedor</th><th class="px-4 py-3 text-center">Pares</th><th class="px-4 py-3">Pago</th><th class="px-4 py-3 text-right">Total</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($sales as $s)
                <tr class="hover:bg-slate-50 {{ $s->status === 'anulada' ? 'opacity-50 line-through' : '' }}">
                    <td class="px-4 py-3"><a href="{{ route('admin.ventas.show', $s) }}" class="font-semibold hover:text-brand-500">{{ $s->code }}</a><p class="text-xs text-slate-400">{{ $s->sold_at->isoFormat('D MMM YYYY HH:mm') }}</p></td>
                    <td class="px-4 py-3">@if($s->customer)<a href="{{ route('admin.clientes.show', $s->customer) }}" class="hover:underline">{{ $s->customer->name }}</a>@else<span class="text-slate-400">Sin cliente</span>@endif</td>
                    <td class="px-4 py-3 text-xs">{{ $s->branch->name }}<br><span class="text-slate-400">{{ $s->seller->name }}</span></td>
                    <td class="px-4 py-3 text-center">{{ $s->items->sum('quantity') }}</td>
                    <td class="px-4 py-3 text-xs">{{ \App\Services\SaleService::PAYMENTS[$s->payment_method] ?? $s->payment_method }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ money($s->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">No hay ventas en estas fechas. Regístralas con «Nueva venta» o diciéndole al asistente 🎙️ «vendí 1 CH-210 talla 41 café a Carlos».</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $sales->links() }}</div>
@endsection
