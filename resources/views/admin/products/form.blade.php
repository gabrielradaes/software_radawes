@extends('admin.layouts.app')
@section('title', $product->exists ? 'Editar: '.$product->name : 'Nuevo producto')
@section('actions')
    @if($product->exists)
        <a href="{{ route('admin.productos.variantes', $product) }}" class="text-sm font-semibold text-brand-500 hover:underline">Variantes y stock →</a>
    @endif
@endsection

@section('content')
<form method="POST" action="{{ $product->exists ? route('admin.productos.update', $product) : route('admin.productos.store') }}" enctype="multipart/form-data" class="grid lg:grid-cols-3 gap-6"
      x-data="{ cat: @js((string) old('category_id', $product->category_id ?? '')), roots: @js($categoryRoots), get root() { return this.roots[this.cat] || null; } }">
    @csrf @if($product->exists) @method('PUT') @endif

    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
            <div class="grid sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <x-admin.field label="Nombre" name="name">
                        <input name="name" value="{{ old('name', $product->name) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </x-admin.field>
                </div>
                <x-admin.field label="Código del modelo" name="code">
                    <input name="code" value="{{ old('code', $product->code) }}" maxlength="40" placeholder="Ej.: CH-210" class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase">
                </x-admin.field>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-admin.field label="Categoría" name="category_id">
                    <select name="category_id" required x-model="cat" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">Elegir…</option>
                        @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $product->category_id) == $c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                </x-admin.field>
                <x-admin.field label="Marca" name="brand_id" help="O escribe una marca nueva abajo">
                    <select name="brand_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">Sin marca</option>
                        @foreach($brands as $b)<option value="{{ $b->id }}" @selected(old('brand_id', $product->brand_id) == $b->id)>{{ $b->name }}</option>@endforeach
                    </select>
                    <input name="new_brand" value="{{ old('new_brand') }}" placeholder="Marca nueva…" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </x-admin.field>
            </div>
            <x-admin.field label="Descripción corta" name="short_description" help="Se muestra en las tarjetas (máx. 255)">
                <input name="short_description" value="{{ old('short_description', $product->short_description) }}" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </x-admin.field>
            <x-admin.field label="Descripción completa" name="description">
                <textarea name="description" rows="6" class="w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('description', $product->description) }}</textarea>
            </x-admin.field>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-admin.field label="Material" name="material"><input name="material" value="{{ old('material', $product->material) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
                <x-admin.field label="País de origen" name="origin_country"><input name="origin_country" value="{{ old('origin_country', $product->origin_country) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
            </div>
        </div>

        {{-- Ficha técnica: campos según la categoría principal elegida --}}
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h2 class="font-semibold mb-1">Ficha técnica</h2>
            <p class="text-xs text-slate-500 mb-4">Los campos cambian según la categoría. Deja vacío lo que no aplique.</p>
            @foreach($specGroups as $group)
                <div x-show="root == {{ $group->id }}" x-cloak class="grid sm:grid-cols-2 gap-3">
                    @foreach($group->specFields as $f)
                        @php $val = old('spec.'.$f->id, $product->exists ? $product->specs->firstWhere('spec_field_id', $f->id)?->value : null); @endphp
                        <div>
                            <label class="block text-xs font-medium mb-1">{{ $f->name }} @if($f->unit)<span class="text-slate-400">({{ $f->unit }})</span>@endif</label>
                            <input name="spec[{{ $f->id }}]" value="{{ $val }}" placeholder="{{ $f->placeholder }}" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                        </div>
                    @endforeach
                </div>
            @endforeach
            <p x-show="!root" class="text-sm text-slate-400">Elige primero una categoría.</p>
        </div>

        {{-- Imágenes --}}
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h2 class="font-semibold mb-3">Imágenes</h2>
            @if($product->exists && $product->images->count())
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-3 mb-4">
                    @foreach($product->images as $img)
                        <div class="relative group rounded-lg overflow-hidden border {{ $img->is_primary ? 'border-brand-500 ring-2 ring-brand-300' : 'border-slate-200' }}">
                            <img src="{{ $img->url() }}" class="w-full aspect-square object-cover">
                            @if($img->is_primary)<span class="absolute top-1 left-1 bg-brand-900 text-white text-[10px] px-1.5 rounded">Principal</span>@endif
                            <div class="absolute inset-x-0 bottom-0 bg-black/60 text-white text-[11px] flex justify-around py-1 opacity-0 group-hover:opacity-100 transition">
                                @unless($img->is_primary)<button type="submit" form="img-primary-{{ $img->id }}" class="hover:underline">Principal</button>@endunless
                                <button type="submit" form="img-del-{{ $img->id }}" class="hover:underline text-rose-300">Quitar</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            <x-admin.field label="Subir imágenes" name="images" help="JPG/PNG/WebP hasta 4 MB. Puedes elegir varias. Consejo: nombra el archivo con el color (ej. chelsea-cafe.jpg).">
                <input type="file" name="images[]" multiple accept="image/*" class="w-full text-sm">
            </x-admin.field>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
            <x-admin.field label="Precio (Bs)" name="base_price">
                <input type="number" step="0.01" min="0" name="base_price" value="{{ old('base_price', $product->base_price) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </x-admin.field>
            <x-admin.field label="Precio antes (oferta)" name="compare_at_price" help="Déjalo vacío si no está en oferta">
                <input type="number" step="0.01" min="0" name="compare_at_price" value="{{ old('compare_at_price', $product->compare_at_price) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </x-admin.field>
            <x-admin.field label="Costo por par (Bs)" name="cost" help="Lo que te cuesta. Se actualiza solo con cada ingreso de mercadería. No se muestra en la página.">
                <input type="number" step="0.01" min="0" name="cost" value="{{ old('cost', $product->cost) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </x-admin.field>
            @if($product->cost && $product->base_price > 0)
                <p class="text-sm text-emerald-700">Ganancia por par: <strong>{{ money($product->base_price - $product->cost) }}</strong> ({{ round((1 - $product->cost / $product->base_price) * 100) }}%)</p>
            @endif
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h2 class="font-semibold mb-1">Se vende en</h2>
            @if($scoped)
                <p class="text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 mt-2">{{ $branches->first()?->name }}</p>
                <p class="text-xs text-slate-400 mt-2">Como supervisor, tus productos se publican en tu tienda. El admin puede compartirlos con otras.</p>
            @else
                <p class="text-xs text-slate-500 mb-3">Marca las tiendas donde se ofrece. Cada una lleva su propio stock.</p>
                <div class="space-y-2">
                    @foreach($branches as $b)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="branches[]" value="{{ $b->id }}" @checked(in_array($b->id, old('branches', $selectedBranches))) class="rounded"> <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $b->color_primary }}"></span> {{ $b->name }}</label>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-3">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="published" value="1" @checked(old('published', (bool) $product->published_at)) class="rounded"> Publicado (visible en el sitio)</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active)) class="rounded"> Activo</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured)) class="rounded"> ★ Destacado en portada</label>
        </div>
        <button class="w-full bg-brand-900 hover:bg-brand-700 text-white font-semibold py-3 rounded-lg">{{ $product->exists ? 'Guardar cambios' : 'Crear producto' }}</button>
        <a href="{{ route('admin.productos.index') }}" class="block text-center text-sm text-slate-500 hover:underline">Cancelar</a>
    </div>
</form>

@if($product->exists)
    @foreach($product->images as $img)
        <form id="img-primary-{{ $img->id }}" method="POST" action="{{ route('admin.imagenes.principal', $img) }}">@csrf</form>
        <form id="img-del-{{ $img->id }}" method="POST" action="{{ route('admin.imagenes.destroy', $img) }}" onsubmit="return confirmar('¿Quitar esta imagen?')">@csrf @method('DELETE')</form>
    @endforeach
@endif
@endsection
