{{--
    Selector de pares: buscar modelo → tocar talla/color (o escanear). No carga todo el stock: busca al momento.
    $name (prefijo de inputs, ej. "items"), $branchId, $withPrice (bool), $optional (bool), $old (filas previas tras un error)
    Emite el evento "items-total" {total, pairs} para que la pantalla muestre el total.
--}}
@php
    $oldRows = collect($old ?? [])->filter(fn ($r) => ! empty($r['variant_id']))->values();
    $oldVariants = $oldRows->isEmpty() ? collect() : \App\Models\ProductVariant::with('product', 'attributeValues.attribute', 'stocks')->whereIn('id', $oldRows->pluck('variant_id'))->get()->keyBy('id');
    $initial = $oldRows->map(function ($r) use ($oldVariants, $branchId) {
        $v = $oldVariants[$r['variant_id']] ?? null;
        if (! $v) {
            return null;
        }

        return ['variant_id' => $v->id, 'label' => trim(($v->product->code ? $v->product->code.' · ' : '').$v->product->name.' · '.$v->label()),
            'price' => $v->effectivePrice(), 'available' => $v->stocks->firstWhere('branch_id', $branchId)?->available() ?? 0,
            'quantity' => (int) ($r['quantity'] ?? 1), 'override' => $r['price'] ?? ''];
    })->filter()->values();
@endphp
<div x-data="itemsPicker(@js($initial))" @pick="add($event.detail)" x-effect="$dispatch('items-total', { total: total(), pairs: pairs() })" class="space-y-4">
    @include('admin.partials.stock-finder', ['branchId' => $branchId, 'pick' => true])

    <div class="space-y-2">
        <template x-for="(it, i) in items" :key="it.variant_id">
            <div class="flex flex-wrap items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2">
                <input type="hidden" :name="`{{ $name }}[${i}][variant_id]`" :value="it.variant_id">
                <span class="flex-1 min-w-[180px] text-sm font-medium" x-text="it.label"></span>
                <div class="flex items-center gap-1">
                    <button type="button" @click="it.quantity > 1 ? it.quantity-- : remove(i)" class="w-8 h-8 rounded-lg bg-white border border-slate-300 text-lg leading-none">−</button>
                    <input type="number" min="1" :max="it.available" :name="`{{ $name }}[${i}][quantity]`" x-model.number="it.quantity" class="w-14 rounded-lg border border-slate-300 px-1 py-1.5 text-center text-sm">
                    <button type="button" @click="it.quantity < it.available && it.quantity++" :disabled="it.quantity >= it.available" class="w-8 h-8 rounded-lg bg-white border border-slate-300 text-lg leading-none disabled:opacity-40">+</button>
                </div>
                @if($withPrice ?? false)
                    <label class="text-xs text-slate-500 flex items-center gap-1">Bs
                        <input type="number" min="0" step="0.01" :name="`{{ $name }}[${i}][price]`" x-model="it.override" :placeholder="it.price.toFixed(2)" class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-sm text-slate-800"></label>
                @endif
                <span class="text-sm font-semibold w-24 text-right" x-text="'Bs ' + line(it).toFixed(2)"></span>
                <button type="button" @click="remove(i)" class="text-rose-600 hover:bg-rose-50 rounded-lg w-8 h-8" title="Quitar">✕</button>
            </div>
        </template>
        <p x-show="!items.length" class="text-sm text-slate-400 border-2 border-dashed border-slate-200 rounded-xl px-4 py-5 text-center">
            {{ ($optional ?? false) ? 'Nada todavía (opcional).' : 'Busca el modelo y toca la talla, o escanea la etiqueta.' }}
        </p>
        <p x-show="items.length" class="text-sm text-right text-slate-600"><span x-text="pairs()"></span> par(es) · <strong class="text-base" x-text="'Bs ' + total().toFixed(2)"></strong></p>
    </div>
</div>

@once
@push('scripts')
<script>
function itemsPicker(initial) {
    return {
        items: initial || [],
        add(d) {
            const row = this.items.find(it => it.variant_id === d.variant_id);
            if (row) { if (row.quantity < d.available) row.quantity++; return; }
            this.items.push({ variant_id: d.variant_id, label: d.label, price: d.price, available: d.available, quantity: 1, override: '' });
        },
        remove(i) { this.items.splice(i, 1); },
        line(it) { return (parseFloat(it.override) || it.price) * (parseInt(it.quantity) || 0); },
        pairs() { return this.items.reduce((s, it) => s + (parseInt(it.quantity) || 0), 0); },
        total() { return this.items.reduce((s, it) => s + this.line(it), 0); },
    };
}
</script>
@endpush
@endonce
