@extends('admin.layouts.app')
@section('title', $post->exists ? 'Editar publicación' : 'Nueva publicación')

@section('content')
<form method="POST" action="{{ $post->exists ? route('admin.publicaciones.update', $post) : route('admin.publicaciones.store') }}" enctype="multipart/form-data" class="grid lg:grid-cols-3 gap-6">
    @csrf @if($post->exists) @method('PUT') @endif
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
            <x-admin.field label="Título" name="title"><input name="title" value="{{ old('title', $post->title) }}" maxlength="160" placeholder="¡Llegaron los Chelsea! 🔥" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
            <x-admin.field label="Texto" name="body" help="Puedes usar **negritas** y emojis. Los saltos de línea se respetan.">
                <textarea name="body" rows="7" class="w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('body', $post->body) }}</textarea>
            </x-admin.field>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-admin.field label="Tipo" name="type">
                    <select name="type" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @foreach(['foto' => 'Foto', 'oferta' => 'Oferta', 'texto' => 'Texto', 'video' => 'Video'] as $k => $v)<option value="{{ $k }}" @selected(old('type', $post->type) === $k)>{{ $v }}</option>@endforeach
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
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h2 class="font-semibold mb-3">Imágenes</h2>
            @if($post->exists && $post->media->count())
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-3 mb-4">
                    @foreach($post->media as $m)
                        <div class="relative group rounded-lg overflow-hidden border border-slate-200">
                            @if($m->type === 'video')
                                <div class="w-full aspect-square bg-slate-900 text-white flex flex-col items-center justify-center text-xs p-2 text-center">▶ Video<br><span class="text-slate-400 break-all">{{ \Illuminate\Support\Str::limit($m->path, 40) }}</span></div>
                            @else
                                <img src="{{ $m->url() }}" class="w-full aspect-square object-cover">
                            @endif
                            <button type="submit" form="media-del-{{ $m->id }}" class="absolute inset-x-0 bottom-0 bg-black/60 text-rose-200 text-[11px] py-1 opacity-0 group-hover:opacity-100">Quitar</button>
                        </div>
                    @endforeach
                </div>
            @endif
            <x-admin.field label="Video (enlace de Facebook, TikTok o YouTube)" name="video_url" help="Pega el enlace del video o reel; se mostrará dentro de la publicación." class="mb-4">
                <input type="url" name="video_url" value="{{ old('video_url') }}" placeholder="https://www.facebook.com/reel/..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </x-admin.field>
            <x-admin.field label="Subir imágenes" name="media" help="Hasta 6 MB cada una. Puedes elegir varias.">
                <input type="file" name="media[]" multiple accept="image/*" class="w-full text-sm">
            </x-admin.field>
            @unless($post->exists)
                <label class="flex items-center gap-2 text-sm mt-3"><input type="checkbox" name="use_product_images" value="1" checked class="rounded"> Si no subo fotos, usar las del producto etiquetado</label>
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <p class="text-sm font-medium mb-2">¿Cómo publicar las fotos y el video?</p>
                    <label class="flex items-start gap-2 text-sm mb-2 cursor-pointer"><input type="radio" name="layout" value="carousel" @checked(old('layout', 'carousel') === 'carousel') class="mt-0.5"> <span><strong>Carrusel</strong> · todo en una sola publicación (el video primero, luego las fotos). <span class="text-slate-400">Recomendado</span></span></label>
                    <label class="flex items-start gap-2 text-sm cursor-pointer"><input type="radio" name="layout" value="split" @checked(old('layout') === 'split') class="mt-0.5"> <span><strong>Separadas</strong> · una publicación aparte por cada foto y por el video, con el mismo texto.</span></label>
                </div>
            @endunless
        </div>
    </div>
    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
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
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="published" value="1" @checked(old('published', $post->exists ? (bool) $post->published_at : true)) class="rounded"> Publicada</label>
            <x-admin.field label="Fecha de publicación" name="published_at" help="Vacío = ahora. Una fecha futura la programa.">
                <input type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </x-admin.field>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $post->is_pinned)) class="rounded"> 📌 Fijar arriba del feed</label>
        </div>
        <button class="w-full bg-brand-900 hover:bg-brand-700 text-white font-semibold py-3 rounded-lg">{{ $post->exists ? 'Guardar cambios' : 'Publicar' }}</button>
        <a href="{{ route('admin.publicaciones.index') }}" class="block text-center text-sm text-slate-500 hover:underline">Cancelar</a>
    </div>
</form>
@if($post->exists)
    @foreach($post->media as $m)
        <form id="media-del-{{ $m->id }}" method="POST" action="{{ route('admin.media.destroy', $m) }}" onsubmit="return confirmar('¿Quitar imagen?')">@csrf @method('DELETE')</form>
    @endforeach
@endif
@endsection
