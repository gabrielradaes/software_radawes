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

<form method="POST" action="{{ route('admin.ventas.store') }}" data-once
      x-data="venta(@js($variants), @js(old('items', [['variant_id' => '', 'quantity' => 1, 'price' => '']])))" class="grid lg:grid-cols-3 gap-6">
    @csrf
    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-6 space-y-4">
        <h3 class="font-semibold">Productos · {{ $branch->name }}</h3>
        @error('items')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
        @if($variants->isEmpty())
            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">No hay productos con stock en esta tienda.</p>
        @endif
        <template x-for="(it, i) in items" :key="i">
            <div class="grid grid-cols-12 gap-2 items-end">
                <label class="col-span-12 sm:col-span-7 text-xs text-slate-500">Producto, talla y color
                    <select :name="`items[${i}][variant_id]`" x-model="it.variant_id" @change="pick(it)" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800">
                        <option value="">Elige…</option>
                        <template x-for="v in variants" :key="v.id"><option :value="v.id" :selected="v.id == it.variant_id" x-text="`${v.label} (hay ${v.stock})`"></option></template>
                    </select></label>
                <label class="col-span-4 sm:col-span-2 text-xs text-slate-500">Cant.
                    <input type="number" min="1" :max="max(it)" :name="`items[${i}][quantity]`" x-model.number="it.quantity" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"></label>
                <label class="col-span-6 sm:col-span-2 text-xs text-slate-500">Precio c/u
                    <input type="number" min="0" step="0.01" :name="`items[${i}][price]`" x-model="it.price" :placeholder="base(it)" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"></label>
                <button type="button" @click="items.splice(i, 1)" x-show="items.length > 1" class="col-span-2 sm:col-span-1 h-10 text-rose-600 hover:bg-rose-50 rounded-lg">✕</button>
            </div>
        </template>
        <button type="button" @click="items.push({variant_id: '', quantity: 1, price: ''})" class="text-sm font-semibold text-brand-700 hover:underline">+ Agregar otro producto</button>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-3">
            <h3 class="font-semibold">Cliente <span class="text-xs text-slate-400 font-normal">(opcional)</span></h3>
            <x-admin.field label="Celular" name="customer_phone">
                <input name="customer_phone" value="{{ old('customer_phone', $customer?->phone) }}" inputmode="tel" placeholder="70011222" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </x-admin.field>
            <x-admin.field label="Nombre (si es nuevo)" name="customer_name">
                <input name="customer_name" value="{{ old('customer_name', $customer?->name) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </x-admin.field>
            <p class="text-xs text-slate-500">Si el celular ya existe se usa su ficha; si no, se registra al cliente.</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <x-admin.field label="Pago" name="payment_method">
                    <select name="payment_method" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach(\App\Services\SaleService::PAYMENTS as $k => $l)<option value="{{ $k }}" @selected(old('payment_method') === $k)>{{ $l }}</option>@endforeach
                    </select>
                </x-admin.field>
                <x-admin.field label="Canal" name="channel">
                    <select name="channel" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach(\App\Services\SaleService::CHANNELS as $k => $l)<option value="{{ $k }}" @selected(old('channel') === $k)>{{ $l }}</option>@endforeach
                    </select>
                </x-admin.field>
            </div>
            <x-admin.field label="Descuento total (Bs.)" name="discount">
                <input type="number" min="0" step="0.01" name="discount" x-model.number="discount" value="{{ old('discount') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </x-admin.field>
            <x-admin.field label="Notas" name="notes"><input name="notes" value="{{ old('notes') }}" maxlength="500" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
            <div class="flex justify-between items-baseline border-t border-slate-100 pt-3"><span class="text-slate-500">Total</span><span class="text-2xl font-bold" x-text="'Bs ' + total().toFixed(2)"></span></div>
            <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-3 rounded-lg" @disabled($variants->isEmpty())>Registrar venta</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
function venta(variants, items) {
    return {
        variants, items, discount: 0,
        find(it) { return this.variants.find(v => v.id == it.variant_id); },
        base(it) { const v = this.find(it); return v ? v.price : ''; },
        max(it) { const v = this.find(it); return v ? v.stock : 999; },
        pick(it) { it.price = ''; },
        total() {
            const sum = this.items.reduce((s, it) => s + (parseFloat(it.price || this.base(it) || 0) * (parseInt(it.quantity) || 0)), 0);
            return Math.max(0, sum - (parseFloat(this.discount) || 0));
        },
    };
}
</script>
@endpush
