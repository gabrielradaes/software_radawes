@extends('admin.layouts.app')
@section('title', 'Apartados')
@section('actions')<a href="{{ route('admin.apartados.create') }}" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ Nuevo apartado</a>@endsection

@section('content')
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Activos</p><p class="text-2xl font-bold">{{ $totals['activos'] }}</p></div>
    <div class="bg-white rounded-xl border {{ $totals['vencidos'] ? 'border-rose-300' : 'border-slate-200' }} p-4"><p class="text-xs text-rose-700">Vencidos</p><p class="text-2xl font-bold text-rose-700">{{ $totals['vencidos'] }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Adelantos recibidos</p><p class="text-xl font-bold">{{ money($totals['adelantos']) }}</p></div>
    <div class="bg-white rounded-xl border border-emerald-200 p-4"><p class="text-xs text-emerald-700">Saldo por cobrar</p><p class="text-xl font-bold text-emerald-700">{{ money($totals['saldo']) }}</p></div>
</div>
<div class="mb-4 flex gap-2 text-sm">
    @foreach(['activo' => 'Activos', 'retirado' => 'Retirados', 'cancelado' => 'Cancelados', 'todos' => 'Todos'] as $k => $l)
        <a href="{{ route('admin.apartados.index', ['estado' => $k]) }}" class="px-3 py-1.5 rounded-full border {{ $estado === $k ? 'bg-brand-900 text-white border-brand-900' : 'border-slate-300 bg-white hover:bg-slate-50' }}">{{ $l }}</a>
    @endforeach
</div>
<div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-4 py-3">Apartado</th><th class="px-4 py-3">Cliente</th><th class="px-4 py-3 text-center">Pares</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-right">Adelanto</th><th class="px-4 py-3 text-right">Saldo</th><th class="px-4 py-3">Vence</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($reservations as $r)
                <tr class="hover:bg-slate-50 {{ $r->status !== 'activo' ? 'opacity-60' : '' }}">
                    <td class="px-4 py-3"><a href="{{ route('admin.apartados.show', $r) }}" class="font-semibold hover:text-brand-500">{{ $r->code }}</a><p class="text-xs text-slate-400">{{ $r->branch->name }} · {{ ucfirst($r->status) }}</p></td>
                    <td class="px-4 py-3"><a href="{{ route('admin.clientes.show', $r->customer) }}" class="hover:underline">{{ $r->customer->name }}</a><p class="text-xs text-slate-400">{{ $r->customer->phone }}</p></td>
                    <td class="px-4 py-3 text-center">{{ $r->items->sum('quantity') }}</td>
                    <td class="px-4 py-3 text-right">{{ money($r->total) }}</td>
                    <td class="px-4 py-3 text-right">{{ money($r->deposit) }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ money($r->balance()) }}</td>
                    <td class="px-4 py-3 text-xs {{ $r->isExpired() ? 'text-rose-600 font-semibold' : '' }}">{{ $r->expires_at->isoFormat('ddd D MMM') }}{{ $r->isExpired() ? ' · vencido' : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">No hay apartados {{ $estado === 'todos' ? '' : $estado.'s' }}.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $reservations->links() }}</div>
@endsection
