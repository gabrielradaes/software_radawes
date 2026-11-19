@extends('layouts.app')
@section('title', $product->name.' · '.$currentBranch->name)
@section('meta_description', $product->short_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 150) ?: $product->name)
@section('og_image', $product->primaryImage?->url() ?? '')

@section('content')
@php
    $firstColor = array_key_first($colors);
    $hasSizes = ! (count($sizes) === 1 && $sizes[0] === '—');
@endphp
<div class="max-w-7xl mx-auto px-4 py-8"
     x-data="{
        matrix: @js($matrix),
        imagesByColor: @js($imagesByColor),
        images: @js($product->images->map->url()->values()),
        color: @js($firstColor),
        size: null,
        current: 0,
        hasSizes: @js($hasSizes),
        get variant() { if (!this.color) return null; const row = this.matrix[this.color] || {}; return this.hasSizes ? (this.size ? row[this.size] : null) : Object.values(row)[0]; },
        get stock() { return this.variant ? this.variant.stock : null; },
        stockOf(size) { const v = (this.matrix[this.color] || {})[size]; return v ? v.stock : 0; },
        pickColor(c) { this.color = c; this.size = null; const img = this.imagesByColor[c]; if (img) { const i = this.images.indexOf(img); if (i >= 0) this.current = i; } },
        waUrl() { const u = new URL(@js(route('whatsapp')), location.origin); u.searchParams.set('producto', @js($product->slug)); u.searchParams.set('origen', 'producto'); if (this.variant) u.searchParams.set('variante', this.variant.id); return u.toString(); }
     }">

    <nav class="text-xs text-brand-500 mb-5 flex items-center gap-1.5">
        <a href="{{ route('home') }}" class="hover:text-brand-900">Inicio</a> ›
        <a href="{{ route('catalog.index') }}" class="hover:text-brand-900">Catálogo</a> ›
        @if($product->category->parent) <a href="{{ route('catalog.category', $product->category->parent) }}" class="hover:text-brand-900">{{ $product->category->parent->name }}</a> › @endif
        <a href="{{ route('catalog.category', $product->category) }}" class="hover:text-brand-900">{{ $product->category->name }}</a> ›
        <span class="text-brand-900">{{ $product->name }}</span>
    </nav>

    <div class="grid lg:grid-cols-2 gap-10">
        {{-- Galería --}}
        <div>
            <div class="relative aspect-square rounded-3xl overflow-hidden bg-brand-100 border border-brand-100">
                <template x-for="(img, i) in images" :key="i">
                    <img :src="img" x-show="current === i" x-transition.opacity class="absolute inset-0 w-full h-full object-cover" alt="{{ $product->name }}">
                </template>
                @if($product->hasDiscount())
                    <span class="absolute top-4 left-4 bg-rose-600 text-white text-sm font-bold px-3 py-1 rounded-full">-{{ $product->discountPercent() }}%</span>
                @endif
                <div class="absolute top-4 right-4 bg-white/90 backdrop-blur rounded-full px-3 py-1.5 shadow">
                    <x-like-button type="product" :id="$product->id" :liked="$liked" :count="$product->likes_count"/>
                </div>
            </div>
            @if($product->images->count() > 1)
                <div class="mt-3 flex gap-3 overflow-x-auto no-scrollbar">
                    @foreach($product->images as $i => $img)
                        <button @click="current = {{ $i }}" :class="current === {{ $i }} ? 'ring-2 ring-brand-900' : 'ring-1 ring-brand-100 opacity-70 hover:opacity-100'" class="shrink-0 w-20 h-20 rounded-xl overflow-hidden">
                            <img src="{{ $img->url() }}" alt="{{ $img->alt }}" class="w-full h-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Info --}}
        <div>
            <p class="text-xs uppercase tracking-[0.2em] text-brand-500 font-semibold">{{ $product->brand?->name }} @if($product->origin_country) · Hecho en {{ $product->origin_country }} @endif</p>
            <h1 class="font-display text-3xl md:text-4xl font-bold mt-2">{{ $product->name }}</h1>
            <p class="text-brand-700 mt-2">{{ $product->short_description }}</p>

            <div class="mt-5 flex items-baseline gap-3">
                <span class="text-3xl font-bold" x-text="variant && variant.price ? 'Bs ' + variant.price.toLocaleString('es-BO', {minimumFractionDigits: 2}) : @js(money($product->base_price))"></span>
                @if($product->hasDiscount())
                    <span class="text-lg text-brand-500 line-through">{{ money($product->compare_at_price) }}</span>
                    <span class="text-sm font-semibold text-rose-600">Ahorras {{ money($product->compare_at_price - $product->base_price) }}</span>
                @endif
            </div>

            {{-- Color --}}
            @if(count($colors) && $firstColor !== '—')
                <div class="mt-7">
                    <p class="text-sm font-semibold mb-2">Color: <span class="font-normal text-brand-700" x-text="color"></span></p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($colors as $name => $hex)
                            <button type="button" @click="pickColor(@js($name))" :class="color === @js($name) ? 'ring-2 ring-offset-2 ring-brand-900' : 'ring-1 ring-brand-200 hover:ring-brand-500'"
                                    class="w-10 h-10 rounded-full transition" style="background: {{ $hex ?? '#999' }}" title="{{ $name }}" aria-label="{{ $name }}"></button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Talla --}}
            @if($hasSizes)
                <div class="mt-6">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-semibold">Talla <span x-show="size" class="font-normal text-brand-700" x-text="'· ' + size"></span></p>
                        <span class="text-xs text-brand-500">Tallas brasileñas (BR)</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($sizes as $s)
                            <button type="button" @click="stockOf(@js($s)) > 0 && (size = @js($s))"
                                    :disabled="stockOf(@js($s)) === 0"
                                    :class="size === @js($s) ? 'bg-brand-900 text-white border-brand-900' : (stockOf(@js($s)) > 0 ? 'bg-white border-brand-200 hover:border-brand-900' : 'bg-brand-50 border-brand-100 text-brand-300 line-through cursor-not-allowed')"
                                    class="relative w-14 h-12 rounded-xl border font-semibold text-sm transition">
                                {{ $s }}
                                <span x-show="stockOf(@js($s)) > 0 && stockOf(@js($s)) <= 2" class="absolute -top-1.5 -right-1.5 bg-gold-500 text-brand-950 text-[9px] font-bold px-1 rounded-full">¡{{ '' }}<span x-text="stockOf(@js($s))"></span>!</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Disponibilidad --}}
            <div class="mt-6 text-sm">
                <template x-if="variant">
                    <p>
                        <span x-show="stock > 0" class="inline-flex items-center gap-1.5 text-emerald-700 font-medium"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Disponible en {{ $branch->name }} <span class="text-brand-500 font-normal" x-text="'(' + stock + ' en stock)'"></span></p>
                        <span x-show="stock === 0" class="inline-flex items-center gap-1.5 text-rose-600 font-medium"><span class="w-2 h-2 rounded-full bg-rose-500"></span> Agotado en esta sucursal: consúltanos, podemos conseguirlo</p>
                    </p>
                </template>
                <template x-if="!variant && hasSizes">
                    <p class="text-brand-500">Elige tu talla para ver disponibilidad. @if($totalStock > 0) <span class="text-emerald-700">{{ $totalStock }} pares en stock.</span> @endif</p>
                </template>
            </div>

            {{-- Guía de tallas y "avísame" --}}
            @if($hasSizes)
                <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm" x-data="{ guia: false, aviso: @js((bool) $errors->any()) }">
                    <button type="button" @click="guia = true" class="text-brand-700 underline underline-offset-2 hover:text-brand-900">📏 Guía de tallas</button>
                    <button type="button" @click="aviso = !aviso" class="text-brand-700 underline underline-offset-2 hover:text-brand-900">🔔 ¿No está tu talla? Avísame cuando llegue</button>

                    <div x-show="guia" x-cloak @keydown.escape.window="guia = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="guia = false">
                        <div class="bg-white rounded-2xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto">
                            <div class="flex justify-between items-start"><h3 class="font-display text-xl font-bold">Guía de tallas</h3><button type="button" @click="guia = false" class="text-2xl leading-none text-brand-400">×</button></div>
                            <p class="text-sm text-brand-600 mt-2"><strong>Cómo medir:</strong> apoya el pie descalzo sobre una hoja contra la pared, marca la punta del dedo más largo y mide en centímetros. Si estás entre dos tallas, elige la más grande.</p>
                            <table class="w-full text-sm mt-4">
                                <thead><tr class="text-left text-xs uppercase text-brand-500 border-b border-brand-100"><th class="py-2">Talla (BR)</th><th>Equivale (EU)</th><th class="text-right">Largo del pie</th></tr></thead>
                                <tbody class="divide-y divide-brand-50">
                                    @foreach(['33' => 22.0, '34' => 22.7, '35' => 23.3, '36' => 24.0, '37' => 24.7, '38' => 25.3, '39' => 26.0, '40' => 26.7, '41' => 27.3, '42' => 28.0, '43' => 28.7, '44' => 29.3, '45' => 30.0] as $br => $cm)
                                        <tr class="{{ in_array($br, $sizes, true) ? '' : 'text-brand-300' }}"><td class="py-1.5 font-semibold">{{ $br }}</td><td>{{ (int) $br + 1 }}</td><td class="text-right">{{ number_format($cm, 1, ',', '') }} cm</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <p class="text-xs text-brand-400 mt-3">Medidas aproximadas. ¿Dudas? Escríbenos por WhatsApp y te ayudamos a elegir.</p>
                        </div>
                    </div>

                    @if(session('restock'))
                        <p class="w-full text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-3 py-2">{{ session('restock') }}</p>
                    @endif
                    <form x-show="aviso" x-cloak method="POST" action="{{ route('products.restock', ['product' => $product->slug]) }}" class="w-full bg-brand-50 border border-brand-100 rounded-2xl p-4 grid sm:grid-cols-2 gap-3">
                        @csrf
                        <p class="sm:col-span-2 text-brand-700">Te escribimos por WhatsApp apenas llegue a {{ $branch->name }}.</p>
                        <input name="name" required maxlength="150" value="{{ old('name', auth('customer')->user()?->name) }}" placeholder="Tu nombre" class="rounded-xl border border-brand-200 px-3 py-2">
                        <input name="phone" required inputmode="tel" value="{{ old('phone', auth('customer')->user()?->phone) }}" placeholder="Celular (ej.: 70011222)" class="rounded-xl border border-brand-200 px-3 py-2">
                        <select name="size" class="rounded-xl border border-brand-200 px-3 py-2" x-init="$el.value = size || ''" x-effect="size && ($el.value = size)">
                            <option value="">Talla…</option>
                            @foreach(range(33, 45) as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
                        </select>
                        <select name="color" class="rounded-xl border border-brand-200 px-3 py-2" x-effect="color && ($el.value = color)">
                            <option value="">Cualquier color</option>
                            @foreach(array_keys($colors) as $c)@if($c !== '—')<option value="{{ $c }}">{{ $c }}</option>@endif @endforeach
                        </select>
                        @if($errors->any())<p class="sm:col-span-2 text-rose-600 text-xs">{{ $errors->first() }}</p>@endif
                        <button class="sm:col-span-2 bg-brand-900 hover:bg-brand-700 text-white font-semibold px-4 py-2.5 rounded-full">Avísame</button>
                    </form>
                </div>
            @endif

            {{-- CTA --}}
            <div class="mt-7 flex flex-col sm:flex-row gap-3">
                <a :href="waUrl()" target="_blank" rel="noopener" class="flex-1 inline-flex items-center justify-center gap-2 bg-wa-500 hover:bg-wa-600 text-white font-semibold px-6 py-3.5 rounded-full text-base transition shadow-lg shadow-wa-500/30">
                    <x-icon-whatsapp class="w-5 h-5"/>
                    <span x-text="variant ? 'Pedir ' + (hasSizes ? 'talla ' + size : color) + ' por WhatsApp' : 'Consultar por WhatsApp'"></span>
                </a>
                <button type="button" x-data @click="navigator.share ? navigator.share({title: @js($product->name), url: location.href}) : navigator.clipboard.writeText(location.href).then(() => alert('Enlace copiado'))"
                        class="inline-flex items-center justify-center gap-2 border border-brand-200 hover:border-brand-900 px-5 py-3.5 rounded-full text-sm font-semibold transition">
                    Compartir
                </button>
            </div>
            <p class="mt-3 text-xs text-brand-500">Te respondemos por WhatsApp al <strong>+{{ $branch->whatsapp }}</strong>. Retiro en tienda o envío a domicilio en {{ $branch->city }}.</p>

            {{-- Detalles --}}
            <div class="mt-8 border-t border-brand-100 pt-6 grid sm:grid-cols-2 gap-4 text-sm">
                @if($product->material)<div><p class="text-brand-500 text-xs uppercase tracking-wider">Material</p><p class="font-medium">{{ $product->material }}</p></div>@endif
                @if($product->origin_country)<div><p class="text-brand-500 text-xs uppercase tracking-wider">Origen</p><p class="font-medium">{{ $product->origin_country }}</p></div>@endif
                <div><p class="text-brand-500 text-xs uppercase tracking-wider">Categoría</p><p class="font-medium">{{ $product->category->name }}</p></div>
                <div><p class="text-brand-500 text-xs uppercase tracking-wider">Garantía</p><p class="font-medium">30 días por defectos de fábrica</p></div>
            </div>
            <div class="mt-6 text-sm text-brand-800 prose-body">{{ $product->description }}</div>

            @if($product->specs->count())
                <div class="mt-8 border-t border-brand-100 pt-6">
                    <h2 class="font-display text-xl font-bold mb-3">Especificaciones</h2>
                    <dl class="divide-y divide-brand-100 text-sm rounded-2xl border border-brand-100 overflow-hidden">
                        @foreach($product->specs as $s)
                            <div class="grid grid-cols-[40%_1fr] gap-3 px-4 py-2.5 {{ $loop->even ? 'bg-brand-50/60' : 'bg-white' }}">
                                <dt class="text-brand-500">{{ $s->field->name }}</dt>
                                <dd class="font-medium text-brand-900">{{ $s->value }}@if($s->field->unit) <span class="text-brand-500 font-normal">{{ $s->field->unit }}</span>@endif</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>
    </div>

    <div class="grid lg:grid-cols-[1fr_360px] gap-10 mt-14">
        <x-comments :model="$product" type="product"/>

        @if($related->count())
            <aside>
                <h3 class="font-display text-xl font-bold mb-4 mt-8 lg:mt-0">También te puede gustar</h3>
                <div class="grid grid-cols-2 gap-4">
                    @foreach($related as $p)
                        <x-product-card :product="$p"/>
                    @endforeach
                </div>
            </aside>
        @endif
    </div>
</div>
@endsection
