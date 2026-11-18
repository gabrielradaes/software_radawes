@extends('admin.layouts.app')
@section('title', 'Venta '.$sale->code)
@section('actions')<a href="{{ route('admin.ventas.index') }}" class="text-sm text-slate-600 hover:underline">← Ventas</a>@endsection

@section('content')
<div class="grid lg:grid-cols-3 gap-6 max-w-5xl">
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div><h2 class="font-bold text-lg">{{ $sale->code }}</h2><p class="text-xs text-slate-500">{{ $sale->sold_at->isoFormat('dddd D [de] MMMM YYYY, HH:mm') }}</p></div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $sale->status === 'anulada' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-800' }}">{{ ucfirst($sale->status) }}</span>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-6 py-2">Producto</th><th class="px-3 py-2 text-center">Cant.</th><th class="px-3 py-2 text-right">P. unit.</th><th class="px-6 py-2 text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($sale->items as $it)
                    <tr><td class="px-6 py-3"><p class="font-medium">{{ $it->variant->product->name }} @if($it->variant->product->code)<span class="text-slate-400 text-xs">({{ $it->variant->product->code }})</span>@endif</p><p class="text-xs text-slate-500">{{ $it->variant->label() }}</p></td>
                        <td class="px-3 py-3 text-center">{{ $it->quantity }}</td><td class="px-3 py-3 text-right">{{ money($it->unit_price) }}</td><td class="px-6 py-3 text-right font-semibold">{{ money($it->line_total) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot class="text-sm">
                @if($sale->discount > 0)<tr><td colspan="3" class="px-6 py-1 text-right text-slate-500">Descuento</td><td class="px-6 py-1 text-right">− {{ money($sale->discount) }}</td></tr>@endif
                <tr><td colspan="3" class="px-6 py-3 text-right font-semibold">Total</td><td class="px-6 py-3 text-right text-lg font-bold">{{ money($sale->total) }}</td></tr>
            </tfoot>
        </table>
    </div>
    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-6 text-sm space-y-2">
            <div class="flex justify-between"><span class="text-slate-500">Cliente</span><span>@if($sale->customer)<a href="{{ route('admin.clientes.show', $sale->customer) }}" class="font-semibold hover:underline">{{ $sale->customer->name }}</a>@else — @endif</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Tienda</span><span>{{ $sale->branch->name }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Vendedor</span><span>{{ $sale->seller->name }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Pago</span><span>{{ \App\Services\SaleService::PAYMENTS[$sale->payment_method] ?? $sale->payment_method }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Canal</span><span>{{ \App\Services\SaleService::CHANNELS[$sale->channel] ?? $sale->channel }}</span></div>
            @if($sale->notes)<p class="pt-2 border-t border-slate-100 whitespace-pre-line text-slate-600">{{ $sale->notes }}</p>@endif
        </div>
        @can('sales.cancel')
            @if($sale->status !== 'anulada')
                <form method="POST" action="{{ route('admin.ventas.anular', $sale) }}" data-once onsubmit="return confirm('¿Anular la venta {{ $sale->code }}? El stock vuelve a la tienda.')" class="bg-white rounded-xl border border-rose-200 p-6 space-y-3">
                    @csrf
                    <h3 class="font-semibold text-rose-700">Anular venta</h3>
                    <input name="reason" maxlength="200" placeholder="Motivo (ej.: cambio de talla)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button class="bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Anular y devolver stock</button>
                </form>
            @endif
        @endcan
    </div>
</div>
@endsection
