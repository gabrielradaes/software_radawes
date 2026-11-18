@extends('admin.layouts.app')
@section('title', 'Ingreso '.$purchase->code)
@section('actions')
    <a href="{{ route('admin.compras.index') }}" class="text-sm text-slate-600 hover:underline">← Compras</a>
    @if(Route::has('admin.etiquetas'))<a href="{{ route('admin.etiquetas', ['ingreso' => $purchase->id]) }}" target="_blank" class="bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">🏷️ Imprimir etiquetas</a>@endif
@endsection

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-5 mb-5 grid sm:grid-cols-5 gap-4 text-sm">
    <div><p class="text-xs text-slate-500">Fecha</p><p class="font-semibold">{{ $purchase->purchased_at->isoFormat('D MMM YYYY') }}</p></div>
    <div><p class="text-xs text-slate-500">Proveedor</p><p class="font-semibold">{{ $purchase->supplier?->name ?? '—' }}</p></div>
    <div><p class="text-xs text-slate-500">Factura</p><p class="font-semibold">{{ $purchase->invoice_number ?? '—' }}</p></div>
    <div><p class="text-xs text-slate-500">Tienda</p><p class="font-semibold">{{ $purchase->branch->name }}</p></div>
    <div><p class="text-xs text-slate-500">Total</p><p class="font-bold text-lg">{{ $purchase->pairs }} pares · {{ money($purchase->total) }}</p></div>
    @if($purchase->notes)<p class="sm:col-span-5 text-slate-600">{{ $purchase->notes }}</p>@endif
</div>
<div class="space-y-4">
    @foreach($groups as $g)
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="flex flex-wrap justify-between gap-2 mb-3">
                <p class="font-semibold">{{ $g['product']->name }} @if($g['product']->code)<span class="text-slate-400 text-sm">({{ $g['product']->code }})</span>@endif @if($g['color'])· {{ $g['color'] }}@endif</p>
                <p class="text-sm text-slate-600">{{ $g['pairs'] }} pares × {{ money($g['cost']) }} = <strong>{{ money($g['total']) }}</strong>
                    @if($g['product']->base_price > 0 && $g['cost'] > 0)<span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">margen {{ round((1 - $g['cost'] / $g['product']->base_price) * 100) }}%</span>@endif</p>
            </div>
            <div class="flex flex-wrap gap-1.5">
                @foreach($g['sizes'] as $size => $q)
                    <span class="text-center border border-slate-200 rounded-lg px-3 py-1.5 text-sm"><span class="block text-[11px] text-slate-500">{{ $size }}</span><strong>{{ $q }}</strong></span>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endsection
