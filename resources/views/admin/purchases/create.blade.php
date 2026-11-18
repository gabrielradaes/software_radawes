@extends('admin.layouts.app')
@section('title', 'Nuevo ingreso de mercadería')
@section('actions')<a href="{{ route('admin.compras.index') }}" class="text-sm text-slate-600 hover:underline">← Compras</a>@endsection

@section('content')
<form method="POST" action="{{ route('admin.compras.store') }}" data-once
      x-data="ingreso(@js($products), @js($curves), @js(old('blocks')))" class="space-y-5">
    @csrf
    <div class="bg-white rounded-xl border border-slate-200 p-5 grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <x-admin.field label="Tienda que recibe" name="branch_id">
            <select name="branch_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach($branches as $b)<option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>@endforeach
            </select>
        </x-admin.field>
        <x-admin.field label="Proveedor" name="supplier_id">
            <select name="supplier_id" x-model="supplier" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">—</option>
                @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id') == $s->id)>{{ $s->name }}</option>@endforeach
                <option value="nuevo">+ Nuevo proveedor…</option>
            </select>
            <input x-show="supplier === 'nuevo'" name="new_supplier" value="{{ old('new_supplier') }}" placeholder="Nombre del proveedor" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </x-admin.field>
        <x-admin.field label="N° de factura / nota" name="invoice_number">
            <input name="invoice_number" value="{{ old('invoice_number') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </x-admin.field>
        <x-admin.field label="Fecha" name="purchased_at">
            <input type="date" name="purchased_at" value="{{ old('purchased_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </x-admin.field>
        <x-admin.field label="Notas" name="notes"><input name="notes" value="{{ old('notes') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
    </div>

    @error('blocks')<p class="text-sm text-rose-600 bg-rose-50 border border-rose-200 rounded-lg p-3">{{ $message }}</p>@enderror
    <div class="bg-sky-50 border border-sky-200 text-sky-900 text-sm rounded-xl px-4 py-3">
        <strong>Cada bloque = un modelo en un color.</strong> Elige la <em>curva</em> para llenar las tallas de un clic (puedes multiplicarla: ×2 = el doble), o escribe los pares a mano. Si el producto todavía no existe, créalo primero en <a href="{{ route('admin.productos.create') }}" class="underline font-semibold" target="_blank">Productos</a> o con el asistente 🎙️.
    </div>

    <template x-for="(b, i) in blocks" :key="b.key">
        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold" x-text="'Bloque ' + (i + 1) + (product(b) ? ' · ' + product(b).label : '')"></h3>
                <button type="button" @click="blocks.splice(i, 1)" x-show="blocks.length > 1" class="text-sm text-rose-600 hover:underline">Quitar</button>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <label class="text-xs text-slate-500 lg:col-span-2">Producto
                    <input type="search" x-model="b.search" placeholder="Buscar por código o nombre…" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800">
                    <select :name="`blocks[${i}][product_id]`" x-model="b.product_id" @change="pickProduct(b)" required size="4" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1 text-sm text-slate-800">
                        <template x-for="p in filtered(b)" :key="p.id"><option :value="p.id" x-text="p.label" :selected="p.id == b.product_id"></option></template>
                    </select>
                </label>
                <label class="text-xs text-slate-500">Color
                    <input :name="`blocks[${i}][color]`" x-model="b.color" list="colores" placeholder="Ej.: Negro" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800">
                    <span class="text-[11px]" x-show="product(b) && product(b).colors.length" x-text="'Tiene: ' + (product(b)?.colors || []).join(', ')"></span>
                </label>
                <label class="text-xs text-slate-500">Costo por par (Bs.)
                    <input type="number" step="0.01" min="0" :name="`blocks[${i}][unit_cost]`" x-model="b.unit_cost" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800">
                </label>
            </div>

            <div x-show="!product(b) || product(b).shoe" class="space-y-2">
                <div class="flex flex-wrap items-end gap-2">
                    <label class="text-xs text-slate-500">Curva
                        <select x-model="b.curve" @change="applyCurve(b)" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800">
                            <option value="">— a mano —</option>
                            <template x-for="c in curves" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                        </select>
                    </label>
                    <label class="text-xs text-slate-500">× veces
                        <input type="number" min="1" max="50" x-model.number="b.factor" @input="applyCurve(b)" class="mt-1 block w-20 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800">
                    </label>
                    <button type="button" @click="b.sizes = {}; b.curve = ''" class="text-xs text-slate-500 hover:underline pb-2">Limpiar</button>
                </div>
                <div class="overflow-x-auto">
                    <div class="flex gap-1.5 min-w-max">
                        <template x-for="s in sizeList(b)" :key="s">
                            <label class="w-14 text-center text-xs text-slate-500">
                                <span x-text="s" class="font-semibold"></span>
                                <input type="number" min="0" :name="`blocks[${i}][sizes][${s}]`" x-model.number="b.sizes[s]" class="mt-1 w-full rounded-lg border border-slate-300 px-1 py-2 text-sm text-center text-slate-800" :class="b.sizes[s] > 0 ? 'bg-emerald-50 border-emerald-300' : ''">
                            </label>
                        </template>
                    </div>
                </div>
            </div>
            <div x-show="product(b) && !product(b).shoe">
                <label class="text-xs text-slate-500">Cantidad
                    <input type="number" min="0" :name="`blocks[${i}][quantity]`" x-model.number="b.quantity" class="mt-1 block w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"></label>
            </div>
            <p class="text-sm text-slate-600"><strong x-text="pairs(b)"></strong> pares · <strong x-text="'Bs ' + (pairs(b) * (parseFloat(b.unit_cost) || 0)).toFixed(2)"></strong></p>
        </div>
    </template>

    <datalist id="colores">@foreach($colors as $c)<option value="{{ $c }}">@endforeach</datalist>

    <div class="flex flex-wrap items-center gap-3">
        <button type="button" @click="add()" class="bg-white border border-slate-300 hover:bg-slate-50 text-sm font-semibold px-4 py-2 rounded-lg">+ Otro bloque</button>
        <button type="button" @click="duplicate()" class="bg-white border border-slate-300 hover:bg-slate-50 text-sm font-semibold px-4 py-2 rounded-lg" title="Mismo producto y curva, otro color">⧉ Mismo modelo, otro color</button>
        <div class="ml-auto text-right">
            <p class="text-sm text-slate-500">Total: <strong class="text-slate-900" x-text="totalPairs() + ' pares'"></strong> · <strong class="text-slate-900 text-lg" x-text="'Bs ' + totalCost().toFixed(2)"></strong></p>
        </div>
        <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-6 py-3 rounded-lg">Registrar ingreso</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
function ingreso(products, curves, old) {
    const SIZES = ['34','35','36','37','38','39','40','41','42','43','44','45','46'];
    let seq = 0;
    const blank = () => ({ key: ++seq, search: '', product_id: '', color: '', curve: '', factor: 1, sizes: {}, quantity: 0, unit_cost: '' });
    const restore = (o) => Object.assign(blank(), { product_id: o.product_id ?? '', color: o.color ?? '', unit_cost: o.unit_cost ?? '', quantity: parseInt(o.quantity) || 0,
        sizes: Object.fromEntries(Object.entries(o.sizes || {}).map(([k, v]) => [k, parseInt(v) || 0])) });
    return {
        products, curves, supplier: @js(old('supplier_id', '')),
        blocks: old ? Object.values(old).map(restore) : [blank()],
        product(b) { return this.products.find(p => p.id == b.product_id); },
        filtered(b) {
            const q = (b.search || '').toLowerCase().trim();
            const list = q ? this.products.filter(p => p.label.toLowerCase().includes(q)) : this.products;
            const cur = this.product(b);
            return cur && !list.includes(cur) ? [cur, ...list.slice(0, 60)] : list.slice(0, 60);
        },
        pickProduct(b) { const p = this.product(b); if (p && !b.unit_cost && p.cost) b.unit_cost = p.cost; },
        sizeList(b) {
            const extra = Object.keys(b.sizes).filter(s => !SIZES.includes(s));
            return [...SIZES, ...extra];
        },
        applyCurve(b) {
            const c = this.curves.find(c => c.id == b.curve);
            if (!c) return;
            const f = Math.max(1, parseInt(b.factor) || 1);
            b.sizes = Object.fromEntries(Object.entries(c.sizes).map(([s, q]) => [s, q * f]));
        },
        pairs(b) {
            const p = this.product(b);
            if (p && !p.shoe) return parseInt(b.quantity) || 0;
            return Object.values(b.sizes).reduce((s, q) => s + (parseInt(q) || 0), 0);
        },
        totalPairs() { return this.blocks.reduce((s, b) => s + this.pairs(b), 0); },
        totalCost() { return this.blocks.reduce((s, b) => s + this.pairs(b) * (parseFloat(b.unit_cost) || 0), 0); },
        add() { this.blocks.push(blank()); },
        duplicate() {
            const last = this.blocks[this.blocks.length - 1];
            this.blocks.push(Object.assign(blank(), { product_id: last.product_id, curve: last.curve, factor: last.factor, sizes: { ...last.sizes }, unit_cost: last.unit_cost }));
        },
    };
}
</script>
@endpush
