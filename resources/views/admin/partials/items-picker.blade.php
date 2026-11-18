{{--
    Selector de pares con escáner. Variables: $variants (VariantOptions), $name (prefijo del input, ej. "items"),
    $withPrice (bool), $old (filas previas). Emite inputs {name}[i][variant_id], [quantity] y opcional [price].
--}}
<div x-data="itemsPicker(@js($variants), @js($old ?? []))" @scanned="addSku($event.detail.code)" class="space-y-3">
    @include('admin.partials.scanner')
    <p x-show="msg" x-text="msg" class="text-sm" :class="ok ? 'text-emerald-700' : 'text-rose-600'"></p>
    <template x-for="(it, i) in items" :key="it.key">
        <div class="grid grid-cols-12 gap-2 items-end">
            <label class="col-span-12 {{ ($withPrice ?? false) ? 'sm:col-span-7' : 'sm:col-span-9' }} text-xs text-slate-500">Producto, talla y color
                <select :name="`{{ $name }}[${i}][variant_id]`" x-model="it.variant_id" {{ ($optional ?? false) ? '' : 'required' }} class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800">
                    <option value="">Elige…</option>
                    <template x-for="v in variants" :key="v.id"><option :value="v.id" :selected="v.id == it.variant_id" x-text="`${v.label} (disp. ${v.stock})`"></option></template>
                </select></label>
            <label class="col-span-4 sm:col-span-2 text-xs text-slate-500">Cant.
                <input type="number" min="1" :max="find(it)?.stock ?? 999" :name="`{{ $name }}[${i}][quantity]`" x-model.number="it.quantity" {{ ($optional ?? false) ? '' : 'required' }} class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"></label>
            @if($withPrice ?? false)
                <label class="col-span-6 sm:col-span-2 text-xs text-slate-500">Precio c/u
                    <input type="number" min="0" step="0.01" :name="`{{ $name }}[${i}][price]`" x-model="it.price" :placeholder="find(it)?.price ?? ''" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"></label>
            @endif
            <button type="button" @click="items.splice(i, 1)" x-show="items.length > 1" class="col-span-2 sm:col-span-1 h-10 text-rose-600 hover:bg-rose-50 rounded-lg">✕</button>
        </div>
    </template>
    <div class="flex justify-between items-center">
        <button type="button" @click="add()" class="text-sm font-semibold text-brand-700 hover:underline">+ Agregar otro</button>
        <p class="text-sm text-slate-600"><span x-text="pairs()"></span> par(es) · <strong x-text="'Bs ' + total().toFixed(2)"></strong></p>
    </div>
</div>

@once
@push('scripts')
<script>
function itemsPicker(variants, old) {
    let seq = 0;
    const row = (o = {}) => ({ key: ++seq, variant_id: o.variant_id ? String(o.variant_id) : '', quantity: parseInt(o.quantity) || 1, price: o.price ?? '' });
    return {
        variants, msg: '', ok: true,
        items: (old && Object.keys(old).length ? Object.values(old) : [{}]).map(row),
        find(it) { return this.variants.find(v => v.id == it.variant_id); },
        add() { this.items.push(row()); },
        pairs() { return this.items.reduce((s, it) => s + (it.variant_id ? (parseInt(it.quantity) || 0) : 0), 0); },
        total() { return this.items.reduce((s, it) => s + (parseFloat(it.price || this.find(it)?.price || 0) * (parseInt(it.quantity) || 0)), 0); },
        addSku(code) {
            const v = this.variants.find(v => (v.sku || '').toUpperCase() === code.toUpperCase());
            if (!v) { this.ok = false; this.msg = `«${code}» no está disponible en esta tienda.`; return; }
            const r = this.items.find(it => it.variant_id == v.id);
            if (r) { if (r.quantity >= v.stock) { this.ok = false; this.msg = `Solo hay ${v.stock} de ${v.label}.`; return; } r.quantity++; }
            else { const empty = this.items.find(it => !it.variant_id); empty ? Object.assign(empty, { variant_id: String(v.id), quantity: 1 }) : this.items.push(row({ variant_id: v.id })); }
            this.ok = true; this.msg = '✔ ' + v.label;
        },
    };
}
</script>
@endpush
@endonce
