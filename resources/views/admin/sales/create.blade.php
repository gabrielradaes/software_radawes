@extends('admin.layouts.app')
@section('title', 'Nueva venta')
@section('actions')<a href="{{ route('admin.ventas.index') }}" class="text-sm text-slate-600 hover:underline">← Ventas</a>@endsection

@section('content')
@unless($scoped)
    <form method="GET" class="mb-4 flex items-center gap-2 text-sm">
        @if($customer)<input type="hidden" name="cliente" value="{{ $customer->id }}">@endif
        <span class="text-slate-500">Tienda:</span>
        <select name="sucursal" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach($branches as $b)<option value="{{ $b->id }}" @selected($b->id === $branch->id)>{{ $b->name }}</option>@endforeach
        </select>
    </form>
@endunless

@unless($cashOpen)
    <p class="mb-4 text-sm bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-2.5">La caja de esta tienda está cerrada: la venta se registra igual, pero no entrará en ningún arqueo. <a href="{{ route('admin.caja', ['sucursal' => $branch->id]) }}" class="font-semibold underline">Abrir caja</a></p>
@endunless

<form method="POST" action="{{ route('admin.ventas.store') }}" data-once
      x-data="venta(@js(old('payment_method', 'efectivo')), @js($customer ? ['name' => $customer->name, 'stage' => $customer->stageLabel()] : null), @js(route('admin.clientes.buscar')))"
      @items-total="subtotal = $event.detail.total; pairs = $event.detail.pairs" class="grid lg:grid-cols-3 gap-6">
    @csrf
    <input type="hidden" name="branch_id" value="{{ $branch->id }}">

    <div class="lg:col-span-2 min-w-0 bg-white rounded-xl border border-slate-200 p-5 space-y-4">
        <h3 class="font-semibold text-lg">1. ¿Qué se lleva? <span class="text-sm font-normal text-slate-500">· {{ $branch->name }}</span></h3>
        @error('items')<p class="text-sm text-rose-600 bg-rose-50 border border-rose-200 rounded-lg px-3 py-2">{{ $message }}</p>@enderror
        @include('admin.partials.items-picker', ['name' => 'items', 'branchId' => $branch->id, 'withPrice' => true, 'old' => old('items')])
    </div>

    <div class="space-y-5">
        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
            <h3 class="font-semibold text-lg">2. Cliente <span class="text-xs text-slate-400 font-normal">(opcional)</span></h3>
            <x-admin.field label="Celular" name="customer_phone">
                <input name="customer_phone" x-model="phone" @input.debounce.400ms="lookup()" value="{{ old('customer_phone', $customer?->phone) }}" inputmode="tel" placeholder="70011222" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </x-admin.field>
            <p x-show="found" class="text-sm text-emerald-700">✔ <strong x-text="found?.name"></strong> <span class="text-xs text-slate-500" x-text="found?.stage"></span></p>
            <div x-show="!found && phone.replace(/\D/g, '').length >= 7">
                <x-admin.field label="Nombre (cliente nuevo)" name="customer_name">
                    <input name="customer_name" value="{{ old('customer_name') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </x-admin.field>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
            <h3 class="font-semibold text-lg">3. Cobrar</h3>
            <input type="hidden" name="payment_method" :value="pay">
            <div class="grid grid-cols-2 gap-2">
                @foreach(['efectivo' => '💵 Efectivo', 'qr' => '📱 QR', 'tarjeta' => '💳 Tarjeta', 'transferencia' => '🏦 Transferencia'] as $k => $l)
                    <button type="button" @click="pay = '{{ $k }}'" :class="pay === '{{ $k }}' ? 'bg-brand-900 text-white border-brand-900' : 'bg-white border-slate-300 hover:border-brand-500'" class="border rounded-xl py-2.5 text-sm font-semibold">{{ $l }}</button>
                @endforeach
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-admin.field label="Descuento (Bs.)" name="discount">
                    <input type="number" min="0" step="0.01" name="discount" x-model.number="discount" value="{{ old('discount') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </x-admin.field>
                <x-admin.field label="Canal" name="channel">
                    <select name="channel" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach(\App\Services\SaleService::CHANNELS as $k => $l)<option value="{{ $k }}" @selected(old('channel') === $k)>{{ $l }}</option>@endforeach
                    </select>
                </x-admin.field>
            </div>
            <div class="flex justify-between items-baseline border-t border-slate-100 pt-3"><span class="text-slate-500" x-text="pairs + ' par(es)'"></span><span class="text-3xl font-bold" x-text="'Bs ' + total().toFixed(2)"></span></div>
            <div x-show="pay === 'efectivo' && total() > 0" class="grid grid-cols-2 gap-3 items-end">
                <label class="text-xs text-slate-500">Recibí (Bs.)<input type="number" min="0" step="0.5" x-model.number="received" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"></label>
                <p class="text-sm pb-2" x-show="received > 0"><span class="text-slate-500">Cambio:</span> <strong class="text-lg" :class="received - total() < 0 ? 'text-rose-600' : 'text-emerald-700'" x-text="received - total() < 0 ? 'falta Bs ' + (total() - received).toFixed(2) : 'Bs ' + (received - total()).toFixed(2)"></strong></p>
            </div>
            <x-admin.field label="Notas" name="notes"><input name="notes" value="{{ old('notes') }}" maxlength="500" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
            <button :disabled="!pairs" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-3.5 rounded-xl text-lg disabled:opacity-40">✔ Registrar venta</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
function venta(pay, customer, lookupUrl) {
    return {
        pay, subtotal: 0, pairs: 0, discount: 0, received: '', phone: @js(old('customer_phone', $customer?->phone ?? '')), found: customer,
        total() { return Math.max(0, this.subtotal - (parseFloat(this.discount) || 0)); },
        async lookup() {
            const digits = this.phone.replace(/\D/g, '');
            if (digits.length < 7) { this.found = null; return; }
            const r = await fetch(lookupUrl + '?phone=' + digits, { headers: { Accept: 'application/json' } });
            const d = await r.json().catch(() => null);
            this.found = d && d.found ? d : null;
        },
    };
}
</script>
@endpush
