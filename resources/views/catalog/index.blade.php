@extends('layouts.app')
@section('title', ($category?->name ?? 'Catálogo').' · '.$currentBranch->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 py-10">
    <nav class="text-xs text-brand-500 mb-3 flex items-center gap-1.5">
        <a href="{{ route('home') }}" class="hover:text-brand-900">Inicio</a> ›
        <a href="{{ route('catalog.index') }}" class="hover:text-brand-900">Catálogo</a>
        @if($category?->parent) › <a href="{{ route('catalog.category', $category->parent) }}" class="hover:text-brand-900">{{ $category->parent->name }}</a> @endif
        @if($category) › <span class="text-brand-900">{{ $category->name }}</span> @endif
    </nav>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl md:text-4xl font-bold">
                @if($term) Resultados para "{{ $term }}" @else {{ $category?->name ?? 'Todo el catálogo' }} @endif
            </h1>
            @if($category?->description)<p class="text-brand-700 mt-1">{{ $category->description }}</p>@endif
            <p class="text-sm text-brand-500 mt-1">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('producto', $products->total()) }}</p>
        </div>
        <form method="GET" class="flex items-center gap-2 text-sm">
            @foreach(request()->except(['orden', 'page']) as $k => $v) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endforeach
            <label class="text-brand-500">Ordenar:</label>
            <select name="orden" onchange="this.form.submit()" class="rounded-lg border border-brand-200 bg-white px-3 py-1.5">
                <option value="nuevos" @selected($sort === 'nuevos')>Más nuevos</option>
                <option value="populares" @selected($sort === 'populares')>Más populares</option>
                <option value="precio-asc" @selected($sort === 'precio-asc')>Precio: menor a mayor</option>
                <option value="precio-desc" @selected($sort === 'precio-desc')>Precio: mayor a menor</option>
            </select>
        </form>
    </div>

    <div class="mt-8 grid lg:grid-cols-[240px_1fr] gap-8">
        {{-- Filtros --}}
        <aside class="space-y-6">
            <div class="bg-white rounded-2xl border border-brand-100 p-5">
                <p class="font-semibold mb-3">Categorías</p>
                <ul class="space-y-1 text-sm">
                    <li><a href="{{ route('catalog.index') }}" class="block px-2 py-1 rounded {{ !$category ? 'bg-brand-900 text-white' : 'hover:bg-brand-50' }}">Todo</a></li>
                    @foreach($rootCategories as $cat)
                        <li>
                            <a href="{{ route('catalog.category', $cat) }}" class="block px-2 py-1 rounded font-medium {{ $category?->id === $cat->id ? 'bg-brand-900 text-white' : 'hover:bg-brand-50' }}">{{ $cat->name }}</a>
                            @if($cat->children->count())
                                <ul class="ml-3 mt-1 space-y-0.5 border-l border-brand-100 pl-2">
                                    @foreach($cat->children as $child)
                                        <li><a href="{{ route('catalog.category', $child) }}" class="block px-2 py-1 rounded text-brand-700 {{ $category?->id === $child->id ? 'bg-brand-100 text-brand-900 font-semibold' : 'hover:bg-brand-50' }}">{{ $child->name }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            <a href="{{ request()->fullUrlWithQuery(['oferta' => request()->boolean('oferta') ? null : 1, 'page' => null]) }}"
               class="block rounded-2xl border p-4 text-sm font-semibold text-center transition {{ request()->boolean('oferta') ? 'bg-rose-600 border-rose-600 text-white' : 'bg-rose-50 border-rose-100 text-rose-700 hover:bg-rose-100' }}">
                🔥 Solo ofertas
            </a>
            <div class="rounded-2xl bg-brand-900 text-white p-5">
                <p class="font-display font-bold text-lg">¿No encuentras tu talla?</p>
                <p class="text-sm text-brand-200 mt-1">Escríbenos y la buscamos por ti.</p>
                <a href="{{ route('whatsapp', ['origen' => 'catalogo']) }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-2 bg-wa-500 hover:bg-wa-600 text-white text-sm font-semibold px-4 py-2 rounded-full">
                    <x-icon-whatsapp class="w-4 h-4"/> WhatsApp
                </a>
            </div>
        </aside>

        {{-- Grilla --}}
        <div>
            @if($products->count())
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-6">
                    @foreach($products as $p)
                        <x-product-card :product="$p"/>
                    @endforeach
                </div>
                <div class="mt-10">{{ $products->links() }}</div>
            @else
                <div class="bg-white rounded-2xl border border-brand-100 p-12 text-center">
                    <p class="font-display text-2xl font-bold">Sin resultados</p>
                    <p class="text-brand-500 mt-2">Prueba con otra búsqueda o categoría.</p>
                    <a href="{{ route('catalog.index') }}" class="mt-5 inline-block bg-brand-900 text-white px-5 py-2 rounded-full text-sm font-semibold">Ver todo el catálogo</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
