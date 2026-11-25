@extends('admin.layouts.app')
@section('title', $post->exists ? 'Editar publicación' : 'Nueva publicación')
@section('actions')<a href="{{ route('admin.publicaciones.index') }}" class="text-sm text-slate-600 hover:underline">← Publicaciones</a>@endsection

@php
    $solids = \App\Models\Post::SOLIDS;
    $gradients = \App\Models\Post::GRADIENTS;
    $bgCss = collect($gradients)->map(fn ($g) => "linear-gradient(135deg, {$g[1]}, {$g[2]})")->all();
    $bgLum = collect($gradients)->map(fn ($g) => (\App\Models\Post::luminance($g[1]) + \App\Models\Post::luminance($g[2])) / 2)->all()
        + collect($solids)->keys()->mapWithKeys(fn ($h) => [$h => \App\Models\Post::luminance($h)])->all();
    $firstImage = $post->exists ? $post->media->firstWhere('type', 'imagen')?->url() : null;
@endphp

@section('content')
<form method="POST" action="{{ $post->exists ? route('admin.publicaciones.update', $post) : route('admin.publicaciones.store') }}" enctype="multipart/form-data"
      x-data="postForm(@js(old('bg', $post->bg)), @js(old('text_color', $post->text_color)), @js($bgCss), @js($bgLum), @js(old('title', $post->title)), @js(old('body', $post->body)), @js($firstImage))"
      @media-changed="image = $event.detail.image || initial" class="grid lg:grid-cols-3 gap-6">
    @csrf @if($post->exists) @method('PUT') @endif
    <div class="lg:col-span-2 min-w-0 space-y-6">
        {{-- 1. Texto --}}
        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
            <h2 class="font-semibold text-lg">1. ¿Qué quieres contar?</h2>
            <x-admin.field label="Título" name="title"><input name="title" x-model="title" value="{{ old('title', $post->title) }}" maxlength="160" placeholder="¡Llegaron los Chelsea! 🔥" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
            <x-admin.field label="Texto" name="body" help="Puedes usar **negritas** y emojis. Los saltos de línea se respetan.">
                <textarea name="body" x-model="body" rows="6" class="w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('body', $post->body) }}</textarea>
            </x-admin.field>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-admin.field label="Tipo" name="type">
                    <select name="type" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @foreach(['foto' => 'Foto', 'oferta' => 'Oferta (sale la etiqueta roja)', 'texto' => 'Solo texto', 'video' => 'Video'] as $k => $v)<option value="{{ $k }}" @selected(old('type', $post->type) === $k)>{{ $v }}</option>@endforeach
                    </select>
                </x-admin.field>
                <x-admin.field label="Producto etiquetado" name="product_id" help="Aparece como tarjeta con precio dentro del post">
                    <select name="product_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">— Ninguno —</option>
                        @foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id', $post->product_id) == $p->id)>{{ $p->name }}</option>@endforeach
                    </select>
                </x-admin.field>
            </div>
        </div>

        {{-- 2. Fotos y videos --}}
        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
            <h2 class="font-semibold text-lg">2. Fotos y videos <span class="text-sm font-normal text-slate-400">(opcional)</span></h2>
            @if($post->exists && $post->media->count())
                <div>
                    <p class="text-sm text-slate-500 mb-2">Ya publicadas:</p>
                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
                        @foreach($post->media as $m)
                            <div class="relative rounded-lg overflow-hidden border border-slate-200">
                                @if($m->isLocalVideo())
                                    <div class="w-full aspect-square bg-slate-900 relative">
                                        @if($m->posterUrl())<img src="{{ $m->posterUrl() }}" class="w-full h-full object-contain">@endif
                                        <span class="absolute inset-0 flex items-center justify-center text-white text-2xl">▶</span>
                                    </div>
                                @elseif($m->type === 'video')
                                    <div class="w-full aspect-square bg-slate-900 text-white flex flex-col items-center justify-center text-xs p-2 text-center">▶ Enlace<br><span class="text-slate-400 break-all">{{ \Illuminate\Support\Str::limit($m->path, 40) }}</span></div>
                                @else
                                    <img src="{{ $m->url() }}" class="w-full aspect-square object-cover">
                                @endif
                                <button type="submit" form="media-del-{{ $m->id }}" class="absolute inset-x-0 bottom-0 bg-black/70 text-rose-100 text-[11px] py-1.5 hover:bg-rose-700">Quitar</button>
                            </div>
                        @endforeach
                    </div>
                    <p class="text-sm text-slate-500 mt-4 mb-1">Agregar más:</p>
                </div>
            @endif

            @include('admin.partials.media-uploader', ['images' => 'media[]', 'videos' => true])

            <details class="text-sm" @if(old('video_url')) open @endif>
                <summary class="cursor-pointer text-slate-600 font-medium">¿El video ya está en Facebook, TikTok o YouTube? Pega el enlace</summary>
                <x-admin.field label="Enlace del video" name="video_url" class="mt-2">
                    <input type="url" name="video_url" value="{{ old('video_url') }}" placeholder="https://www.facebook.com/reel/..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </x-admin.field>
            </details>

            @unless($post->exists)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="use_product_images" value="1" checked class="rounded"> Si no subo fotos, usar las del producto etiquetado</label>
                <div class="border-t border-slate-100 pt-4">
                    <p class="text-sm font-medium mb-2">Si subes varias, ¿cómo se publican?</p>
                    <label class="flex items-start gap-2 text-sm mb-2 cursor-pointer"><input type="radio" name="layout" value="carousel" @checked(old('layout', 'carousel') === 'carousel') class="mt-0.5"> <span><strong>Carrusel</strong> · todo en una sola publicación, en el orden de arriba. <span class="text-slate-400">Recomendado</span></span></label>
                    <label class="flex items-start gap-2 text-sm cursor-pointer"><input type="radio" name="layout" value="split" @checked(old('layout') === 'split') class="mt-0.5"> <span><strong>Separadas</strong> · una publicación por cada foto o video, con el mismo texto.</span></label>
                </div>
            @endunless
        </div>

        {{-- 3. Color --}}
        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
            <div>
                <h2 class="font-semibold text-lg">3. Color de la publicación</h2>
                <p class="text-sm text-slate-500">Si no lleva fotos y el texto es corto, sale en letras grandes sobre el color (como los estados de colores de Facebook).</p>
            </div>
            <input type="hidden" name="bg" :value="bg">
            <input type="hidden" name="text_color" :value="fgMode === 'auto' ? '' : fg">
            <div class="flex flex-wrap gap-2 items-center">
                <button type="button" @click="bg = ''" :class="!bg ? 'ring-2 ring-offset-2 ring-brand-500' : ''" class="w-10 h-10 rounded-full border border-slate-300 bg-white text-[10px] text-slate-500" title="Normal (blanco)">Normal</button>
                @foreach($solids as $hex => $label)
                    <button type="button" @click="bg = '{{ $hex }}'" :class="bg === '{{ $hex }}' ? 'ring-2 ring-offset-2 ring-brand-500' : ''" class="w-10 h-10 rounded-full border border-black/10" style="background: {{ $hex }}" title="{{ $label }}"></button>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                @foreach($gradients as $key => [$label, $a, $b])
                    <button type="button" @click="bg = '{{ $key }}'" :class="bg === '{{ $key }}' ? 'ring-2 ring-offset-2 ring-brand-500' : ''" class="h-10 px-3 rounded-full text-white text-xs font-semibold shadow-sm" style="background: linear-gradient(135deg, {{ $a }}, {{ $b }})">{{ $label }}</button>
                @endforeach
                <label class="flex items-center gap-2 text-sm text-slate-600 ml-1">Otro: <input type="color" :value="bg && bg.startsWith('#') ? bg : '#7c3aed'" @input="bg = $event.target.value" class="w-10 h-10 rounded border border-slate-300 p-0.5"></label>
            </div>
            <div x-show="bg" class="flex flex-wrap items-center gap-3 text-sm border-t border-slate-100 pt-3">
                <span class="text-slate-600">Color de letra:</span>
                <label class="flex items-center gap-1.5"><input type="radio" value="auto" x-model="fgMode"> Automático <span class="text-slate-400">(se lee siempre bien)</span></label>
                <label class="flex items-center gap-1.5"><input type="radio" value="custom" x-model="fgMode"> Elegir: <input type="color" x-model="fg" @input="fgMode = 'custom'" class="w-9 h-9 rounded border border-slate-300 p-0.5"></label>
                <p x-show="fgMode === 'custom' && lowContrast()" class="w-full text-amber-700 text-xs">⚠ Con estos colores el texto se lee difícil. Prueba "Automático".</p>
            </div>
        </div>
    </div>

    <div class="space-y-6 min-w-0">
        {{-- Vista previa --}}
        <div class="lg:sticky lg:top-20 space-y-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Así se verá</p>
                <div class="rounded-2xl overflow-hidden shadow-sm border border-slate-200" :style="cardStyle()">
                    <div class="flex items-center gap-2 px-3 py-2.5">
                        <span class="w-8 h-8 rounded-full bg-brand-900 text-gold-400 text-[10px] font-bold flex items-center justify-center">RW</span>
                        <div class="text-xs leading-tight"><p class="font-semibold">{{ $scoped ? $branches->first()?->name : 'Tu tienda' }}</p><p :class="bg ? 'opacity-75' : 'text-slate-500'">Ahora</p></div>
                    </div>
                    <template x-if="big()">
                        <div class="px-5 py-10 min-h-[200px] flex flex-col items-center justify-center text-center">
                            <p class="font-display font-extrabold text-xl leading-tight" x-text="title"></p>
                            <p class="mt-2 font-semibold whitespace-pre-line" x-text="body"></p>
                        </div>
                    </template>
                    <template x-if="image">
                        <img :src="image" class="w-full aspect-square object-cover">
                    </template>
                    <template x-if="!big()">
                        <div class="px-3 py-3">
                            <p class="font-display font-bold" x-text="title || (body ? '' : 'Escribe un título…')"></p>
                            <p class="text-sm mt-1 line-clamp-4 whitespace-pre-line" :class="bg ? '' : 'text-slate-700'" x-text="body"></p>
                        </div>
                    </template>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
                <h2 class="font-semibold">4. Publicar</h2>
                @if($scoped)
                    <div>
                        <p class="text-sm font-medium mb-1">Tienda</p>
                        <p class="text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2">{{ $branches->first()?->name }}</p>
                        <input type="hidden" name="branch_id" value="{{ $scoped }}">
                    </div>
                @else
                    <x-admin.field label="Tienda" name="branch_id" help="Vacío = se ve en todas las tiendas">
                        <select name="branch_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="">Todas las tiendas</option>
                            @foreach($branches as $b)<option value="{{ $b->id }}" @selected(old('branch_id', $post->branch_id) == $b->id)>{{ $b->name }}</option>@endforeach
                        </select>
                    </x-admin.field>
                @endif
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="published" value="1" @checked(old('published', $post->exists ? (bool) $post->published_at : true)) class="rounded"> Visible en la página <span class="text-slate-400">(sin marcar = borrador)</span></label>
                <x-admin.field label="Fecha de publicación" name="published_at" help="Vacío = ahora. Una fecha futura la programa.">
                    <input type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </x-admin.field>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $post->is_pinned)) class="rounded"> 📌 Fijar arriba del feed</label>
                <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg text-lg">{{ $post->exists ? '✔ Guardar cambios' : '✔ Publicar' }}</button>
                <a href="{{ route('admin.publicaciones.index') }}" class="block text-center text-sm text-slate-500 hover:underline">Cancelar</a>
            </div>
        </div>
    </div>
</form>
@if($post->exists)
    @foreach($post->media as $m)
        <form id="media-del-{{ $m->id }}" method="POST" action="{{ route('admin.media.destroy', $m) }}" onsubmit="return confirmar('¿Quitar de la publicación?')">@csrf @method('DELETE')</form>
    @endforeach
@endif
@endsection

@push('scripts')
<script>
function postForm(bg, textColor, gradients, lum, title, body, image) {
    return {
        bg: bg || '', fg: textColor || '#ffffff', fgMode: textColor ? 'custom' : 'auto', title: title || '', body: body || '', image,
        initial: image,
        luminance(hex) {
            if (!hex || hex[0] !== '#') return lum[hex] ?? 0;
            const c = [1, 3, 5].map((i) => parseInt(hex.substr(i, 2), 16) / 255).map((v) => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4));
            return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
        },
        autoFg() { return this.luminance(this.bg) > 0.179 ? '#111827' : '#ffffff'; },
        textFg() { return this.fgMode === 'auto' ? this.autoFg() : this.fg; },
        lowContrast() {
            const a = this.luminance(this.bg), b = this.luminance(this.fg);
            return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05) < 3;
        },
        cardStyle() {
            if (!this.bg) return 'background:#fff;color:#0f172a';
            const css = this.bg.startsWith('#') ? this.bg : gradients[this.bg];
            return `background:${css};color:${this.textFg()};border-color:transparent`;
        },
        big() { return this.bg && !this.image && (this.title + this.body).trim() !== '' && (this.title + this.body).length <= 220; },
    };
}
</script>
@endpush
