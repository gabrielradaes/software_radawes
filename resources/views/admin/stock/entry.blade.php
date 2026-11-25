@extends('admin.layouts.app')
@section('title', 'Ingresar calzado al stock')
@section('actions')
    <a href="{{ route('admin.stock.index') }}" class="text-sm text-slate-600 hover:underline">← Volver a stock</a>
@endsection

@section('content')
@php
    $oldQ = old('q', []);
    $old = [
        'mode' => old('product_id') ? 'buscar' : (old('name') ? 'nuevo' : null),
        'product_id' => old('product_id'),
        'colors' => array_values(array_filter(old('colors', []), fn ($c) => $c !== null && $c !== '')),
        'tallas' => array_values(old('tallas', [])),
        'q' => $oldQ,
    ];
@endphp

@if($done)
    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
        <p class="font-semibold text-emerald-900">Guardado. ¿Y ahora?</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="{{ route('admin.productos.variantes', ['product' => $done, 'sucursal' => request('sucursal')]) }}" class="bg-white border border-emerald-300 hover:bg-emerald-100 text-emerald-900 text-sm font-semibold px-4 py-2 rounded-lg">📦 Ver tallas y stock</a>
            <a href="{{ route('admin.etiquetas', ['producto' => $done->id, 'sucursal' => request('sucursal')]) }}" target="_blank" class="bg-white border border-emerald-300 hover:bg-emerald-100 text-emerald-900 text-sm font-semibold px-4 py-2 rounded-lg">🏷️ Imprimir etiquetas</a>
            @can('products.manage')
                <a href="{{ route('admin.productos.edit', $done) }}" class="bg-white border border-emerald-300 hover:bg-emerald-100 text-emerald-900 text-sm font-semibold px-4 py-2 rounded-lg">{{ $done->images()->exists() ? '✏️ Editar el modelo' : '📷 Agregar fotos al modelo' }}</a>
            @endcan
            @can('posts.manage')
                <a href="{{ route('admin.publicaciones.create', ['producto' => $done->id]) }}" class="bg-white border border-emerald-300 hover:bg-emerald-100 text-emerald-900 text-sm font-semibold px-4 py-2 rounded-lg">📣 Publicarlo</a>
            @endcan
        </div>
        <p class="text-xs text-emerald-800 mt-3">O ingresa otro modelo aquí abajo 👇</p>
    </div>
@endif

<form method="POST" action="{{ route('admin.stock.ingresar.store') }}" enctype="multipart/form-data" class="space-y-5 max-w-4xl"
      x-data="stockEntry(@js([
          'searchUrl' => route('admin.stock.buscar'),
          'modelUrl' => route('admin.stock.modelo', ['product' => '__ID__']),
          'branchId' => $scoped ?? (int) old('branch_id', request('sucursal', $branches->first()?->id)),
          'preset' => $preset,
          'prefill' => $prefill,
          'old' => $old,
      ]))" @submit="submit($event)">
    @csrf

    {{-- 1. Modelo --}}
    <section class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6">
        <h2 class="font-bold text-lg"><span class="inline-flex w-7 h-7 rounded-full bg-brand-900 text-white text-sm items-center justify-center mr-1">1</span> ¿Qué modelo llegó?</h2>

        @if(! $scoped)
            <div class="mt-4 max-w-xs">
                <label class="block text-sm font-medium mb-1">Tienda donde llegaron</label>
                <select name="branch_id" x-model.number="branchId" @change="product && loadModel()" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </div>
        @else
            <p class="mt-2 text-sm text-slate-500">Se ingresa en <strong>{{ $branches->first()?->name }}</strong>.</p>
        @endif

        <div class="mt-4 grid grid-cols-2 gap-2 max-w-md">
            <button type="button" @click="setMode('buscar')" :class="mode === 'buscar' ? 'bg-brand-900 text-white border-brand-900' : 'bg-white hover:bg-slate-50'" class="border border-slate-300 rounded-xl px-3 py-3 text-sm font-semibold">🔎 Ya existe<br><span class="font-normal text-xs opacity-80">buscar por código o nombre</span></button>
            <button type="button" @click="setMode('nuevo')" :class="mode === 'nuevo' ? 'bg-brand-900 text-white border-brand-900' : 'bg-white hover:bg-slate-50'" class="border border-slate-300 rounded-xl px-3 py-3 text-sm font-semibold">➕ Modelo nuevo<br><span class="font-normal text-xs opacity-80">nunca lo registramos</span></button>
        </div>

        {{-- Buscar uno que ya existe --}}
        <div x-show="mode === 'buscar'" x-cloak class="mt-4">
            <template x-if="!product">
                <div>
                    <input type="search" x-model="query" @input.debounce.250ms="search()" @keydown.enter.prevent="results[0] && pick(results[0])" placeholder="Código o nombre (ej.: CH-210, mocasín)…" autocomplete="off"
                           class="w-full rounded-lg border border-slate-300 px-4 py-3 text-base">
                    <p x-show="searching" class="text-xs text-slate-500 mt-2">Buscando…</p>
                    <p x-show="!searching && query.length >= 2 && !results.length" class="text-sm text-slate-600 mt-3">No encontré ese modelo. Si es nuevo, toca <button type="button" class="font-semibold text-brand-700 underline" @click="setMode('nuevo')">➕ Modelo nuevo</button>.</p>
                    <div class="mt-2 divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden" x-show="results.length">
                        <template x-for="r in results" :key="r.id">
                            <button type="button" @click="pick(r)" class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-slate-50 text-left">
                                <img :src="r.image || ''" x-show="r.image" class="w-11 h-11 rounded-lg object-cover bg-slate-100">
                                <span x-show="!r.image" class="w-11 h-11 rounded-lg bg-slate-100 flex items-center justify-center">👞</span>
                                <span class="flex-1 min-w-0"><span class="font-semibold block truncate" x-text="r.name"></span><span class="text-xs text-slate-500" x-text="(r.code ? r.code + ' · ' : '') + 'hay ' + r.available + ' par(es) en esta tienda'"></span></span>
                                <span class="text-brand-700 text-sm font-semibold">Elegir</span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
            <template x-if="product">
                <div class="flex items-center gap-3 rounded-xl border-2 border-emerald-300 bg-emerald-50 px-4 py-3">
                    <img :src="product.image || ''" x-show="product.image" class="w-12 h-12 rounded-lg object-cover">
                    <div class="flex-1 min-w-0"><p class="font-semibold truncate" x-text="product.name"></p><p class="text-xs text-slate-600" x-text="product.code || 'sin código'"></p></div>
                    <button type="button" @click="clearProduct()" class="text-sm text-slate-600 hover:underline">Cambiar</button>
                </div>
            </template>
            <input type="hidden" name="product_id" :value="mode === 'buscar' && product ? product.id : ''">
        </div>

        {{-- Modelo nuevo --}}
        <div x-show="mode === 'nuevo'" x-cloak class="mt-4 space-y-4">
            <div class="grid sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Nombre del modelo</label>
                    <input name="name" value="{{ old('name') }}" :disabled="mode !== 'nuevo'" maxlength="160" placeholder="Ej.: Botín Chelsea (si no tiene, queda Marca + código)" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Código</label>
                    <input name="code" value="{{ old('code', $prefill['code'] ?? '') }}" :disabled="mode !== 'nuevo'" maxlength="40" placeholder="Ej.: CH-210" class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase">
                </div>
            </div>
            <div class="grid sm:grid-cols-4 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Marca</label>
                    <input name="brand" value="{{ old('brand', $prefill['brand'] ?? '') }}" :disabled="mode !== 'nuevo'" list="marcas" maxlength="120" placeholder="Ej.: Demócrata" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <datalist id="marcas">@foreach($brands as $b)<option value="{{ $b }}">@endforeach</datalist>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Clase *</label>
                    <select name="category_id" :disabled="mode !== 'nuevo'" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">Elegir…</option>
                        @foreach($classes as $c)<option value="{{ $c['id'] }}" @selected(old('category_id') == $c['id'])>{{ $c['name'] }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Precio de venta (Bs) *</label>
                    <input type="number" step="0.01" min="0" name="price" value="{{ old('price') }}" :disabled="mode !== 'nuevo'" inputmode="decimal" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Material</label>
                    <input name="material" value="{{ old('material') }}" :disabled="mode !== 'nuevo'" list="materiales" placeholder="Ej.: Cuero" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <datalist id="materiales"><option value="Cuero"><option value="Cuero graso"><option value="Gamuza"><option value="Nobuck"><option value="Charol"><option value="Sintético"></datalist>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="show_web" value="0" :disabled="mode !== 'nuevo'"><input type="checkbox" name="show_web" value="1" :disabled="mode !== 'nuevo'" @checked(old('show_web', '1') === '1') class="rounded"> Mostrarlo en la página de la tienda</label>
            <div>
                <p class="text-sm font-medium mb-2">Fotos (opcional, también puedes ponerlas después)</p>
                @include('admin.partials.media-uploader', ['images' => 'images[]', 'videos' => false])
            </div>
        </div>
    </section>

    {{-- 2. Colores y tallas --}}
    <section x-show="ready" x-cloak class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6">
        <h2 class="font-bold text-lg"><span class="inline-flex w-7 h-7 rounded-full bg-brand-900 text-white text-sm items-center justify-center mr-1">2</span> Colores y tallas</h2>

        <div class="mt-4">
            <p class="text-sm font-medium mb-2">Colores</p>
            <div class="flex flex-wrap gap-2">
                <template x-for="c in colors" :key="c">
                    <span class="inline-flex items-center gap-1.5 bg-brand-900 text-white text-sm rounded-full pl-3 pr-1.5 py-1">
                        <span x-text="c"></span>
                        <button type="button" @click="removeColor(c)" class="w-5 h-5 rounded-full hover:bg-white/20" title="Quitar">×</button>
                    </span>
                </template>
                <span x-show="!colors.length" class="text-sm text-slate-500">Sin color (una sola fila). Agrega los colores que llegaron 👇</span>
            </div>
            <div class="flex gap-2 mt-2 max-w-sm">
                <input x-model="newColor" @keydown.enter.prevent="addColor()" list="colores-conocidos" placeholder="Ej.: Negro, Café…" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button type="button" @click="addColor()" class="bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold px-4 rounded-lg">+ Color</button>
            </div>
            <datalist id="colores-conocidos">@foreach($knownColors as $c)<option value="{{ $c }}">@endforeach</datalist>
        </div>

        <div class="mt-5">
            <p class="text-sm font-medium mb-2">Tallas <span class="text-slate-400 font-normal">(toca para marcar)</span></p>
            <div class="flex flex-wrap gap-1.5">
                <template x-for="t in sizeChoices()" :key="t">
                    <button type="button" @click="toggleTalla(t)" :class="tallas.includes(t) ? 'bg-brand-900 text-white border-brand-900' : 'bg-white hover:bg-slate-50'" class="border border-slate-300 rounded-lg min-w-11 h-10 px-2 text-sm font-semibold" x-text="t"></button>
                </template>
            </div>
            <div class="flex flex-wrap gap-2 mt-2 items-center">
                <button type="button" @click="range(38, 43)" class="text-xs border border-slate-300 rounded-lg px-2.5 py-1.5 hover:bg-slate-50">38 al 43</button>
                <button type="button" @click="range(37, 42)" class="text-xs border border-slate-300 rounded-lg px-2.5 py-1.5 hover:bg-slate-50">37 al 42</button>
                <button type="button" @click="range(39, 44)" class="text-xs border border-slate-300 rounded-lg px-2.5 py-1.5 hover:bg-slate-50">39 al 44</button>
                <button type="button" @click="tallas = []" class="text-xs border border-slate-300 rounded-lg px-2.5 py-1.5 hover:bg-slate-50">Sin talla</button>
                <input x-model="newTalla" @keydown.enter.prevent="addTalla()" placeholder="Otra (ej.: 47)" class="w-28 rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                <button type="button" @click="addTalla()" class="text-xs bg-slate-800 text-white rounded-lg px-2.5 py-1.5">+ Talla</button>
            </div>
        </div>
        <template x-for="(c, ci) in colors" :key="'hc' + c"><input type="hidden" :name="'colors[' + ci + ']'" :value="c"></template>
        <template x-for="t in tallas" :key="'ht' + t"><input type="hidden" name="tallas[]" :value="t"></template>
    </section>

    {{-- 3. Cantidades --}}
    <section x-show="ready" x-cloak class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-bold text-lg"><span class="inline-flex w-7 h-7 rounded-full bg-brand-900 text-white text-sm items-center justify-center mr-1">3</span> ¿Cuántos pares llegaron?</h2>
            <div class="ml-auto flex items-center gap-1.5 text-xs">
                <span class="text-slate-500">Poner</span>
                <input type="number" min="0" max="999" x-model.number="fillN" class="w-14 rounded-lg border border-slate-300 px-2 py-1 text-center">
                <button type="button" @click="fillAll()" class="border border-slate-300 rounded-lg px-2.5 py-1 hover:bg-slate-50">en todas</button>
                <button type="button" @click="qty = {}" class="border border-slate-300 rounded-lg px-2.5 py-1 hover:bg-slate-50">Limpiar</button>
            </div>
        </div>
        <p class="text-xs text-slate-500 mt-1">Escribe solo lo que <strong>llegó ahora</strong>: se suma a lo que ya había. Deja vacío lo que no llegó.</p>

        <div class="mt-4 overflow-x-auto -mx-1 px-1">
            <table class="text-sm border-separate border-spacing-1">
                <thead>
                    <tr>
                        <th class="text-left text-xs text-slate-500 font-medium pr-2">Color \ Talla</th>
                        <template x-for="t in cols()" :key="'th' + t"><th class="text-xs font-semibold text-slate-700 min-w-14" x-text="t || 'Cantidad'"></th></template>
                        <th class="text-xs text-slate-500 font-medium pl-2">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(c, ci) in rows()" :key="'r' + c">
                        <tr>
                            <td class="font-semibold pr-2 whitespace-nowrap" x-text="c || 'Único'"></td>
                            <template x-for="t in cols()" :key="'c' + c + t">
                                <td class="text-center align-top">
                                    <input type="number" min="0" max="9999" inputmode="numeric" :name="'q[' + ci + '][' + (t || '_') + ']'" x-model="qty[key(c, t)]"
                                           class="w-14 rounded-lg border px-1 py-2 text-center font-semibold" :class="+qty[key(c, t)] > 0 ? 'border-emerald-500 bg-emerald-50' : 'border-slate-300'">
                                    <span x-show="have[key(c, t)] !== undefined" class="block text-[10px] text-slate-400 mt-0.5" x-text="'hay ' + (have[key(c, t)] || 0)"></span>
                                </td>
                            </template>
                            <td class="pl-2 font-bold tabular-nums" x-text="rowTotal(c)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="mt-5 grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Nota (opcional)</label>
                <input name="note" value="{{ old('note') }}" maxlength="200" placeholder="Ej.: llegó de São Paulo, factura 1234" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Costo por par (Bs, opcional)</label>
                <input type="number" step="0.01" min="0" name="cost" value="{{ old('cost') }}" inputmode="decimal" placeholder="Lo que te costó, no se muestra en la página" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>
    </section>

    {{-- Guardar --}}
    <div x-show="ready" x-cloak class="sticky bottom-0 z-10 -mx-4 sm:mx-0 bg-white/95 backdrop-blur border-t sm:border border-slate-200 sm:rounded-2xl px-4 py-3 flex flex-wrap items-center gap-3 shadow-lg">
        <p class="text-sm"><strong class="text-lg tabular-nums" x-text="total()"></strong> par(es) <span class="text-slate-500" x-text="summary()"></span></p>
        <button type="submit" :disabled="!canSave()" class="ml-auto bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-semibold px-6 py-3 rounded-xl"
                x-text="mode === 'nuevo' && total() === 0 ? 'Crear modelo sin pares' : 'Guardar ingreso'"></button>
    </div>
</form>
@endsection

@push('scripts')
<script>
function stockEntry(cfg) {
    const fold = (s) => (s || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);
    const sortT = (a) => a.sort((x, y) => (isNaN(x) || isNaN(y)) ? String(x).localeCompare(String(y)) : parseFloat(x) - parseFloat(y));
    return {
        mode: cfg.old.mode || (cfg.preset ? 'buscar' : (cfg.prefill.code ? 'nuevo' : null)),
        branchId: cfg.branchId,
        query: '', results: [], searching: false, product: null,
        colors: cfg.old.colors || [], tallas: (cfg.old.tallas || []).map(String), qty: {}, have: {},
        newColor: '', newTalla: '', fillN: 1, extraSizes: [], keepPrefill: false,

        init() {
            // Vuelve con lo que se había escrito si hubo un error
            const q = cfg.old.q || {};
            this.rows().forEach((c, ci) => this.cols().forEach((t) => {
                const v = (q[ci] || {})[t || '_'];
                if (v !== undefined && v !== null && v !== '') this.qty[this.key(c, t)] = v;
            }));
            // Viene del asistente con un modelo dictado: color, talla y 1 par ya marcados
            if (!cfg.old.mode && (cfg.prefill.color || cfg.prefill.talla)) {
                if (cfg.prefill.color) this.colors = [cap(cfg.prefill.color)];
                if (cfg.prefill.talla) this.tallas = [String(cfg.prefill.talla)];
                this.qty[this.key(this.colors[0] || '', this.tallas[0] || '')] = 1;
                this.keepPrefill = true;
            }
            const pid = cfg.old.product_id || (cfg.preset && cfg.preset.id);
            if (pid) { this.product = { id: pid, name: (cfg.preset && cfg.preset.name) || 'Modelo', code: cfg.preset && cfg.preset.code }; this.loadModel(!!cfg.old.product_id); }
        },
        get ready() { return this.mode === 'nuevo' || (this.mode === 'buscar' && !!this.product); },
        setMode(m) { this.mode = m; if (m === 'nuevo') { this.product = null; this.have = {}; } },

        async search() {
            const q = this.query.trim();
            if (q.length < 2) { this.results = []; return; }
            this.searching = true;
            try {
                const r = await fetch(cfg.searchUrl + '?q=' + encodeURIComponent(q) + '&sucursal=' + this.branchId, { headers: { Accept: 'application/json' } });
                this.results = r.ok ? await r.json() : [];
            } catch (e) { this.results = []; }
            this.searching = false;
        },
        pick(r) { this.product = r; this.results = []; this.query = ''; this.qty = {}; this.loadModel(false); },
        clearProduct() { this.product = null; this.colors = []; this.tallas = []; this.qty = {}; this.have = {}; this.$nextTick(() => this.$root.querySelector('input[type=search]')?.focus()); },
        async loadModel(keepSelection) {
            try {
                const r = await fetch(cfg.modelUrl.replace('__ID__', this.product.id) + '?sucursal=' + this.branchId, { headers: { Accept: 'application/json' } });
                if (!r.ok) return;
                const d = await r.json();
                this.product = Object.assign({}, this.product, d.product);
                const cells = (d.cells || {})[d.branch_id] || {};
                const have = {};
                Object.keys(cells).forEach((c) => Object.keys(cells[c]).forEach((t) => {
                    have[this.key(c === 'Único' ? '' : c, t === 'Única' ? '' : t)] = cells[c][t].available;
                }));
                this.have = have;
                if (this.keepPrefill) {   // del modelo, más el color y la talla dictados
                    this.keepPrefill = false;
                    d.colors.map((c) => c.name).filter((n) => n !== 'Único' && !this.colors.some((x) => fold(x) === fold(n))).forEach((n) => this.colors.push(n));
                    this.tallas = sortT([...new Set([...this.tallas, ...d.sizes.filter((s) => s !== 'Única').map(String)])]);
                } else if (!keepSelection) {
                    this.colors = d.colors.map((c) => c.name).filter((n) => n !== 'Único');
                    this.tallas = d.sizes.filter((s) => s !== 'Única').map(String);
                }
            } catch (e) { /* sin internet: igual se puede escribir */ }
        },

        key(c, t) { return fold(c) + '|' + (t || ''); },
        rows() { return this.colors.length ? this.colors : ['']; },
        cols() { return this.tallas.length ? this.tallas : ['']; },
        sizeChoices() {
            const base = ['34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'];
            return sortT([...new Set([...base, ...this.extraSizes, ...this.tallas])]);
        },
        addColor() {
            const c = cap(this.newColor.trim().replace(/\s+/g, ' '));
            if (c && !this.colors.some((x) => fold(x) === fold(c))) this.colors.push(c);
            this.newColor = '';
        },
        removeColor(c) { this.colors = this.colors.filter((x) => x !== c); },
        toggleTalla(t) { this.tallas = this.tallas.includes(t) ? this.tallas.filter((x) => x !== t) : sortT([...this.tallas, t]); },
        range(a, b) { const r = []; for (let i = a; i <= b; i++) r.push(String(i)); this.tallas = sortT([...new Set([...this.tallas, ...r])]); },
        addTalla() {
            const t = this.newTalla.trim().replace(',', '.');
            if (/^[0-9A-Za-z.\/ ]{1,10}$/.test(t)) { if (!this.extraSizes.includes(t)) this.extraSizes.push(t); if (!this.tallas.includes(t)) this.toggleTalla(t); }
            this.newTalla = '';
        },
        fillAll() { const n = Math.max(0, parseInt(this.fillN) || 0); this.rows().forEach((c) => this.cols().forEach((t) => { this.qty[this.key(c, t)] = n || ''; })); },
        rowTotal(c) { return this.cols().reduce((s, t) => s + (parseInt(this.qty[this.key(c, t)]) || 0), 0); },
        total() { return this.rows().reduce((s, c) => s + this.rowTotal(c), 0); },
        summary() {
            if (this.mode === 'buscar' && this.product) return 'de ' + this.product.name;
            const n = this.$root.querySelector('input[name=name]');
            return n && n.value ? 'de ' + n.value + ' (nuevo)' : '';
        },
        canSave() { return this.mode === 'nuevo' || (this.mode === 'buscar' && !!this.product && this.total() > 0); },
        submit(e) {
            if (!this.canSave()) { e.preventDefault(); return; }
            if (this.mode === 'buscar' && this.product) {
                const hadColors = Object.keys(this.have).some((k) => k.split('|')[0] !== '');
                if (hadColors && !this.colors.length) { e.preventDefault(); alert('Este modelo tiene colores: agrega de qué color llegaron los pares.'); return; }
            }
            const msg = this.total() > 0 ? '¿Ingresar ' + this.total() + ' par(es) ' + this.summary() + '?' : '¿Crear el modelo sin pares por ahora?';
            if (!confirm(msg)) e.preventDefault();
        },
    };
}
</script>
@endpush
