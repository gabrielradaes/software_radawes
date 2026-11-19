{{--
    Buscador de modelos con grilla colores × tallas.
    $branchId  tienda (para disponibilidad)
    $pick      true = tocar una casilla emite el evento "pick" {variant_id, label, price, available, sku}
--}}
@php $pick = $pick ?? false; @endphp
<div x-data="stockFinder({ branch: {{ (int) $branchId }}, pick: @js($pick), urls: { search: @js(route('admin.stock.buscar')), model: @js(route('admin.stock.modelo', ['product' => '__ID__'])), sku: @js(route('admin.stock.sku')) } })"
     @scanned="bySku($event.detail.code)" class="space-y-3 min-w-0">
    <div class="flex gap-2 items-center">
        <div class="relative flex-1">
            <input type="search" x-model="q" @input.debounce.250ms="search()" @keydown.enter.prevent="enter()" @keydown.escape="results = []"
                   placeholder="Busca por código o nombre del modelo (ej.: CH-210, chelsea)…" autocomplete="off"
                   class="w-full rounded-xl border-2 border-brand-200 bg-white pl-10 pr-3 py-3 text-[15px] focus:border-brand-600 focus:outline-none">
            <svg class="w-5 h-5 absolute left-3 top-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
            <div x-show="results.length" x-cloak @click.outside="results = []" class="absolute z-30 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg max-h-96 overflow-y-auto">
                <template x-for="r in results" :key="r.id">
                    <button type="button" @click="open(r.id)" class="w-full flex items-center gap-3 px-3 py-2 hover:bg-slate-50 text-left">
                        <span class="w-10 h-10 rounded-lg bg-slate-100 overflow-hidden shrink-0"><img x-show="r.image" :src="r.image" class="w-full h-full object-cover" alt=""></span>
                        <span class="flex-1 min-w-0"><span class="block text-sm font-semibold truncate"><span x-show="r.code" class="text-brand-700" x-text="r.code + ' · '"></span><span x-text="r.name"></span></span>
                            <span class="text-xs text-slate-500" x-text="'Bs ' + r.price.toFixed(2)"></span></span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="r.available ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'" x-text="r.available ? r.available + ' disp.' : 'sin stock'"></span>
                    </button>
                </template>
            </div>
        </div>
        @include('admin.partials.scanner-button')
    </div>
    <p x-show="msg" x-text="msg" class="text-sm" :class="ok ? 'text-emerald-700' : 'text-rose-600'"></p>
    <p x-show="loading" class="text-sm text-slate-400">Buscando…</p>

    <template x-if="m">
        <div class="bg-white border border-slate-200 rounded-xl p-4 space-y-3 min-w-0">
            <div class="flex items-center gap-3">
                <span class="w-14 h-14 rounded-lg bg-slate-100 overflow-hidden shrink-0"><img x-show="m.product.image" :src="m.product.image" class="w-full h-full object-cover" alt=""></span>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold"><span x-show="m.product.code" class="text-brand-700" x-text="m.product.code + ' · '"></span><span x-text="m.product.name"></span></p>
                    <p class="text-xs text-slate-500"><span x-text="m.product.category"></span> · <span x-text="'Bs ' + m.product.price.toFixed(2)"></span></p>
                </div>
                <a x-show="m.product.edit" :href="m.product.edit" class="text-xs font-semibold text-brand-700 hover:underline whitespace-nowrap">Editar stock</a>
                <button type="button" @click="m = null" class="text-slate-400 text-xl leading-none px-1" title="Cerrar">×</button>
            </div>
            <p x-show="pick" class="text-xs text-slate-500">Toca la talla y el color para agregarla.</p>
            <template x-for="b in m.branches" :key="b.id">
                <div x-show="b.id === m.branch_id || showOthers" class="overflow-x-auto">
                    <p class="text-xs font-semibold uppercase tracking-wide mb-1" :class="b.id === m.branch_id ? 'text-brand-800' : 'text-slate-400'" x-text="(b.id === m.branch_id ? '📍 ' : '') + b.name + ' · ' + total(b.id) + ' disponibles'"></p>
                    <table class="text-sm border-separate border-spacing-1">
                        <thead><tr><th></th><template x-for="s in m.sizes" :key="s"><th class="text-xs text-slate-500 font-semibold w-11" x-text="s"></th></template></tr></thead>
                        <tbody>
                            <template x-for="c in m.colors" :key="c.name">
                                <tr>
                                    <td class="pr-2 whitespace-nowrap text-xs"><span class="inline-block w-3 h-3 rounded-full border border-black/10 align-middle mr-1" :style="'background:' + (c.hex || '#ddd')"></span><span x-text="c.name"></span></td>
                                    <template x-for="s in m.sizes" :key="s">
                                        <td>
                                            <button type="button" @click="choose(b.id, c.name, s)" :disabled="!canPick(b.id, c.name, s)"
                                                    :title="cell(b.id, c.name, s)?.reserved ? cell(b.id, c.name, s).reserved + ' apartado(s)' : ''"
                                                    class="w-11 h-10 rounded-lg text-sm font-bold relative"
                                                    :class="!cell(b.id, c.name, s) ? 'bg-transparent' : (cell(b.id, c.name, s).available > 0 ? (b.id === m.branch_id ? 'bg-emerald-100 text-emerald-900 ' + (pick ? 'hover:bg-emerald-600 hover:text-white cursor-pointer' : 'cursor-default') : 'bg-sky-50 text-sky-800 cursor-default') : 'bg-slate-50 text-slate-300 cursor-default')">
                                                <span x-text="cell(b.id, c.name, s) ? (cell(b.id, c.name, s).available || '–') : ''"></span>
                                                <span x-show="cell(b.id, c.name, s)?.reserved" class="absolute -top-1 -right-1 bg-amber-400 text-[9px] text-amber-950 rounded-full px-1" x-text="'+' + cell(b.id, c.name, s)?.reserved"></span>
                                            </button>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
            <button type="button" x-show="m.branches.length > 1" @click="showOthers = !showOthers" class="text-xs font-semibold text-brand-700 hover:underline" x-text="showOthers ? 'Ocultar otras tiendas' : 'Ver otras tiendas (' + m.branches.filter(b => b.id !== m.branch_id).map(b => b.name + ': ' + total(b.id)).join(', ') + ')'"></button>
            <p class="text-[11px] text-slate-400">Número = pares disponibles para vender (sin contar los apartados, en amarillo).</p>
        </div>
    </template>
</div>

@once
@push('scripts')
<script>
function stockFinder(cfg) {
    return {
        q: '', results: [], m: null, msg: '', ok: true, loading: false, showOthers: false, pick: cfg.pick,
        async get(url) { const r = await fetch(url, { headers: { Accept: 'application/json' } }); return { ok: r.ok, data: await r.json().catch(() => ({})) }; },
        async search() {
            const q = this.q.trim();
            if (q.length < 2) { this.results = []; return; }
            this.loading = true;
            const r = await this.get(cfg.urls.search + '?sucursal=' + cfg.branch + '&q=' + encodeURIComponent(q));
            this.loading = false;
            if (q !== this.q.trim()) return;   // llegó una búsqueda más nueva
            this.results = Array.isArray(r.data) ? r.data : [];
            this.msg = this.results.length ? '' : 'No encontré modelos con «' + q + '».'; this.ok = !!this.results.length;
        },
        async enter() {
            const q = this.q.trim();
            if (!q) return;
            // Lector de códigos: si es un código de barras exacto, se usa ese par
            if (!/\s/.test(q)) {
                const r = await this.get(cfg.urls.sku + '?sucursal=' + cfg.branch + '&code=' + encodeURIComponent(q));
                if (r.ok && r.data.ok) { this.results = []; this.q = ''; return this.bySku(q); }
            }
            if (this.results.length) return this.open(this.results[0].id);
            await this.search();
            if (this.results.length === 1) this.open(this.results[0].id);
        },
        async open(id) {
            this.results = []; this.loading = true;
            const r = await this.get(cfg.urls.model.replace('__ID__', id) + '?sucursal=' + cfg.branch);
            this.loading = false;
            if (r.ok) { this.m = r.data; this.msg = ''; this.showOthers = this.total(r.data.branch_id) === 0; }
        },
        cell(b, c, s) { return this.m?.cells?.[b]?.[c]?.[s] || null; },
        total(b) { let t = 0; for (const c of Object.values(this.m?.cells?.[b] || {})) for (const x of Object.values(c)) t += x.available; return t; },
        canPick(b, c, s) { const x = this.cell(b, c, s); return this.pick && b === this.m.branch_id && x && x.available > 0; },
        choose(b, c, s) {
            if (!this.canPick(b, c, s)) return;
            const x = this.cell(b, c, s);
            const label = (this.m.product.code ? this.m.product.code + ' · ' : '') + this.m.product.name + ' · Talla ' + s + ' · ' + c;
            this.$dispatch('pick', { variant_id: x.variant_id, sku: x.sku, label: label.replace(' · Talla Única', '').replace(' · Único', ''), price: x.price, available: x.available });
            this.ok = true; this.msg = '✔ Agregado: talla ' + s + ' ' + c;
        },
        async bySku(code) {
            const r = await this.get(cfg.urls.sku + '?sucursal=' + cfg.branch + '&code=' + encodeURIComponent(code));
            if (!r.ok || !r.data.ok) { this.ok = false; this.msg = r.data.message || 'Código no encontrado.'; return; }
            if (this.pick) {
                if (r.data.available < 1) { this.ok = false; this.msg = 'Sin stock disponible: ' + r.data.label; return; }
                this.$dispatch('pick', { variant_id: r.data.variant_id, sku: r.data.sku, label: r.data.label, price: r.data.price, available: r.data.available });
                this.ok = true; this.msg = '✔ ' + r.data.label;
            } else {
                this.open(r.data.product_id);
            }
        },
    };
}
</script>
@endpush
@endonce
