@extends('admin.layouts.app')
@section('title', 'Compras · ingresos de mercadería')
@section('actions')
    <a href="{{ route('admin.proveedores.index') }}" class="text-sm text-slate-600 hover:underline">Proveedores</a>
    <a href="{{ route('admin.curvas.index') }}" class="text-sm text-slate-600 hover:underline">Curvas de tallas</a>
    <a href="{{ route('admin.compras.create') }}" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ Nuevo ingreso</a>
@endsection

@section('content')
<div class="grid grid-cols-2 gap-4 mb-5 max-w-md">
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Pares ingresados (mes)</p><p class="text-2xl font-bold">{{ $totals['pares'] }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Invertido (mes)</p><p class="text-2xl font-bold">{{ money($totals['monto']) }}</p></div>
</div>
<form method="GET" class="mb-4 flex gap-2">
    <select name="proveedor" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Todos los proveedores</option>
        @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(request('proveedor') == $s->id)>{{ $s->name }}</option>@endforeach
    </select>
</form>
<div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-4 py-3">Ingreso</th><th class="px-4 py-3">Proveedor</th><th class="px-4 py-3">Tienda</th><th class="px-4 py-3 text-center">Pares</th><th class="px-4 py-3 text-right">Costo total</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($purchases as $p)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3"><a href="{{ route('admin.compras.show', $p) }}" class="font-semibold hover:text-brand-500">{{ $p->code }}</a><p class="text-xs text-slate-400">{{ $p->purchased_at->isoFormat('D MMM YYYY') }} @if($p->invoice_number)· Fact. {{ $p->invoice_number }}@endif · {{ $p->user->name }}</p></td>
                    <td class="px-4 py-3">{{ $p->supplier?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs">{{ $p->branch->name }}</td>
                    <td class="px-4 py-3 text-center">{{ $p->pairs }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ money($p->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Aún no hay ingresos. Registra la mercadería que llega del proveedor con «Nuevo ingreso».</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $purchases->links() }}</div>
@endsection
