@extends('admin.layouts.app')
@section('title', 'Kardex · '.$stock->variant->product->name.' · '.$stock->variant->label())
@section('actions')<a href="{{ url()->previous() }}" class="text-sm text-slate-600 hover:underline">← Volver</a>@endsection

@section('content')
<div class="grid sm:grid-cols-4 gap-4 mb-5 max-w-3xl">
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Sucursal</p><p class="font-semibold">{{ $stock->branch->name }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">SKU</p><p class="font-mono">{{ $stock->variant->sku }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Stock actual</p><p class="text-2xl font-bold">{{ $stock->quantity }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Mínimo</p><p class="text-2xl font-bold">{{ $stock->min_quantity }}</p></div>
</div>
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden max-w-3xl">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Tipo</th><th class="px-4 py-3 text-right">Cantidad</th><th class="px-4 py-3 text-right">Saldo</th><th class="px-4 py-3">Motivo</th><th class="px-4 py-3">Usuario</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($stock->movements as $m)
                <tr>
                    <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap">{{ $m->created_at->isoFormat('D MMM YYYY HH:mm') }}</td>
                    <td class="px-4 py-2.5 capitalize">{{ $m->type }}</td>
                    <td class="px-4 py-2.5 text-right font-semibold {{ $m->quantity >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">{{ $m->quantity >= 0 ? '+' : '' }}{{ $m->quantity }}</td>
                    <td class="px-4 py-2.5 text-right">{{ $m->balance_after }}</td>
                    <td class="px-4 py-2.5 text-slate-600">{{ $m->reason }} @if($m->reference_type)<span class="text-xs text-slate-400">({{ class_basename($m->reference_type) }} #{{ $m->reference_id }})</span>@endif</td>
                    <td class="px-4 py-2.5 text-slate-600">{{ $m->user?->name ?? 'Sistema' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Sin movimientos registrados (stock inicial cargado por seed).</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
