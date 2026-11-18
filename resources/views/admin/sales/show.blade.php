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

{{-- Cambios y devoluciones --}}
<div class="max-w-5xl mt-6 space-y-4">
    @foreach($sale->returns as $ret)
        <div class="bg-white rounded-xl border border-slate-200 p-5 text-sm">
            <p class="font-semibold">🔁 {{ $ret->type === 'cambio' ? 'Cambio' : 'Devolución' }} · {{ $ret->created_at->isoFormat('D MMM YYYY HH:mm') }} · {{ $ret->user->name }} @if($ret->reason)<span class="font-normal text-slate-500">— {{ $ret->reason }}</span>@endif</p>
            <ul class="mt-2 space-y-0.5">
                @foreach($ret->items as $it)
                    <li>{{ $it->direction === 'in' ? '↩︎ Devolvió' : '↪︎ Se llevó' }} {{ $it->quantity }} × {{ $it->variant->product->name }} {{ $it->variant->label() }} ({{ money($it->unit_price) }})</li>
                @endforeach
            </ul>
            <p class="mt-2 font-semibold {{ $ret->balance > 0 ? 'text-emerald-700' : ($ret->balance < 0 ? 'text-rose-700' : 'text-slate-600') }}">
                {{ $ret->balance > 0 ? 'Cobró '.money($ret->balance).' de diferencia' : ($ret->balance < 0 ? 'Se devolvió '.money(-$ret->balance).' al cliente' : 'Sin diferencia de precio') }}
                @if($ret->balance != 0)({{ \App\Services\SaleService::PAYMENTS[$ret->payment_method] ?? $ret->payment_method }})@endif</p>
        </div>
    @endforeach

    @if($sale->status !== 'anulada' && auth()->user()->can('sales.create'))
        <details class="bg-white rounded-xl border border-slate-200 p-5" @if($errors->has('exchange')) open @endif>
            <summary class="cursor-pointer font-semibold">🔁 Cambio de talla o devolución</summary>
            <form method="POST" action="{{ route('admin.ventas.cambio', $sale) }}" data-once class="mt-4 space-y-5">
                @csrf
                @error('exchange')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                <div>
                    <p class="text-sm font-medium mb-2">1. ¿Qué devuelve el cliente?</p>
                    <div class="space-y-2">
                        @foreach($sale->items as $it)
                            @php $left = $it->quantity - (int) ($returned[$it->id] ?? 0); @endphp
                            <label class="flex flex-wrap items-center gap-3 text-sm {{ $left ? '' : 'opacity-50' }}">
                                <input type="number" name="returns[{{ $it->id }}]" min="0" max="{{ $left }}" value="{{ old('returns.'.$it->id, 0) }}" @disabled(! $left) class="w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-center">
                                <span>de {{ $left }} · {{ $it->variant->product->name }} {{ $it->variant->label() }} ({{ money($it->unit_price) }} c/u)</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="text-sm font-medium mb-2">2. ¿Qué se lleva a cambio? <span class="font-normal text-slate-500">(déjalo vacío si solo devuelve)</span></p>
                    @include('admin.partials.items-picker', ['name' => 'items', 'withPrice' => false, 'optional' => true, 'old' => old('items')])
                </div>
                <div class="grid sm:grid-cols-3 gap-3 items-end">
                    <x-admin.field label="La diferencia se paga / devuelve en" name="payment_method">
                        <select name="payment_method" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach(\App\Services\SaleService::PAYMENTS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                        </select>
                    </x-admin.field>
                    <x-admin.field label="Motivo" name="reason"><input name="reason" maxlength="200" placeholder="Ej.: le quedó chico" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
                    <button class="bg-brand-900 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg">Registrar cambio</button>
                </div>
                <p class="text-xs text-slate-500">Lo devuelto vuelve al stock, lo que se lleva sale del stock, y la diferencia de precio entra o sale de la caja abierta.</p>
            </form>
        </details>
    @endif
</div>
@endsection
