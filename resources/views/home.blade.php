@extends('layouts.app')
@section('title', $branch->name.' · '.$branch->tagline)

@section('content')
@php $palette = \App\Support\Theme::resolve($currentBranch)['palette']; @endphp
{{-- Hero --}}
<section class="relative bg-white overflow-hidden border-b border-brand-100">
    <div class="absolute inset-0 opacity-60" style="background-image: radial-gradient(circle at 15% 25%, {{ $palette['brand'][100] }} 0, transparent 45%), radial-gradient(circle at 85% 75%, {{ $palette['gold'][400] }}33 0, transparent 40%);"></div>
    <div class="relative max-w-7xl mx-auto px-4 py-16 md:py-24 grid md:grid-cols-2 gap-10 items-center">
        <div>
            <p class="text-gold-600 text-sm font-semibold tracking-[0.25em] uppercase">{{ $branch->brand_tagline ?? 'Hecho en Brasil' }} · {{ $branch->city }}</p>
            <h1 class="mt-4 font-display text-4xl md:text-6xl font-bold leading-[1.05] text-brand-900">{{ $branch->tagline }}</h1>
            <p class="mt-5 text-brand-700 text-lg max-w-md">{{ $branch->description }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('catalog.index') }}" class="bg-brand-900 hover:bg-brand-700 text-white font-semibold px-6 py-3 rounded-full transition shadow-lg shadow-brand-900/20">Ver catálogo</a>
                <a href="{{ route('whatsapp', ['origen' => 'hero']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 bg-wa-500 hover:bg-wa-600 text-white font-semibold px-6 py-3 rounded-full transition">
                    <x-icon-whatsapp class="w-5 h-5"/> Escríbenos
                </a>
            </div>
            <div class="mt-10 flex gap-8 text-sm">
                <div><p class="font-display text-2xl font-bold text-brand-900">100%</p><p class="text-brand-500">Cuero legítimo</p></div>
                <div><p class="font-display text-2xl font-bold text-brand-900">38–44</p><p class="text-brand-500">Tallas disponibles</p></div>
                <div><p class="font-display text-2xl font-bold text-brand-900">30 días</p><p class="text-brand-500">Garantía</p></div>
            </div>
        </div>
        <div class="hidden md:grid grid-cols-2 gap-4">
            @foreach($featured->take(4) as $i => $p)
                <a href="{{ route('products.show', $p) }}" class="rounded-2xl overflow-hidden aspect-[4/5] {{ $i % 2 ? 'translate-y-8' : '' }} ring-1 ring-brand-100 shadow-md hover:shadow-xl hover:ring-gold-500/60 transition">
                    @if($p->primaryImage)<img src="{{ $p->primaryImage->url() }}" alt="{{ $p->name }}" class="w-full h-full object-cover">@endif
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- Categorías --}}
<section class="max-w-7xl mx-auto px-4 -mt-8 relative z-10">
    <div class="bg-white rounded-2xl shadow-lg border border-brand-100 p-3 flex gap-2 overflow-x-auto no-scrollbar">
        @foreach($rootCategories as $cat)
            <a href="{{ route('catalog.category', $cat) }}" class="shrink-0 px-5 py-2.5 rounded-xl bg-brand-50 hover:bg-brand-900 hover:text-white font-medium text-sm transition">{{ $cat->name }}</a>
            @foreach($cat->children as $child)
                <a href="{{ route('catalog.category', $child) }}" class="shrink-0 px-4 py-2.5 rounded-xl hover:bg-brand-100 text-sm text-brand-700 transition">{{ $child->name }}</a>
            @endforeach
        @endforeach
        <a href="{{ route('catalog.index', ['oferta' => 1]) }}" class="shrink-0 px-5 py-2.5 rounded-xl bg-rose-50 text-rose-700 hover:bg-rose-600 hover:text-white font-medium text-sm transition">🔥 Ofertas</a>
    </div>
</section>

{{-- Destacados --}}
<section class="max-w-7xl mx-auto px-4 mt-16">
    <div class="flex items-end justify-between mb-6">
        <div>
            <p class="text-xs uppercase tracking-[0.2em] text-brand-500 font-semibold">Lo más pedido</p>
            <h2 class="font-display text-3xl font-bold mt-1">Destacados</h2>
        </div>
        <a href="{{ route('catalog.index', ['orden' => 'populares']) }}" class="text-sm font-semibold text-brand-500 hover:text-brand-900">Ver todo →</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
        @foreach($featured as $p)
            <x-product-card :product="$p"/>
        @endforeach
    </div>
</section>

{{-- Feed --}}
<section class="bg-white border-y border-brand-100 mt-20 py-16">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-end justify-between mb-8">
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-brand-500 font-semibold">Comunidad</p>
                <h2 class="font-display text-3xl font-bold mt-1">Novedades</h2>
            </div>
            <a href="{{ route('feed.index') }}" class="text-sm font-semibold text-brand-500 hover:text-brand-900">Ver todas →</a>
        </div>
        <div class="grid md:grid-cols-3 gap-6">
            @foreach($posts as $post)
                <x-post-card :post="$post"/>
            @endforeach
        </div>
    </div>
</section>

{{-- Nuevos --}}
<section class="max-w-7xl mx-auto px-4 mt-16">
    <div class="flex items-end justify-between mb-6">
        <div>
            <p class="text-xs uppercase tracking-[0.2em] text-brand-500 font-semibold">Recién llegados</p>
            <h2 class="font-display text-3xl font-bold mt-1">Nuevos modelos</h2>
        </div>
        <a href="{{ route('catalog.index') }}" class="text-sm font-semibold text-brand-500 hover:text-brand-900">Ver catálogo →</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
        @foreach($newest as $p)
            <x-product-card :product="$p"/>
        @endforeach
    </div>
</section>

{{-- Ubicación --}}
<section class="max-w-7xl mx-auto px-4 mt-20">
    <div class="bg-white border border-brand-100 shadow-sm text-brand-900 rounded-3xl overflow-hidden grid md:grid-cols-2">
        <div class="p-10 md:p-14">
            <p class="text-gold-600 text-xs uppercase tracking-[0.2em] font-semibold">Visítanos</p>
            <h2 class="font-display text-3xl md:text-4xl font-bold mt-2">{{ $branch->name }}</h2>
            <ul class="mt-6 space-y-3 text-brand-700">
                <li class="flex gap-3"><span>📍</span><span>{{ $branch->address }}<br>{{ $branch->city }}, {{ $branch->country }}</span></li>
                @if($branch->opening_hours)<li class="flex gap-3"><span>🕘</span><span>{{ $branch->opening_hours }}</span></li>@endif
                <li class="flex gap-3"><span>📱</span><a href="{{ route('whatsapp', ['origen' => 'ubicacion']) }}" target="_blank" rel="noopener" class="text-wa-600 font-semibold hover:text-brand-900">+{{ $branch->whatsapp }}</a></li>
            </ul>
            <div class="mt-6 flex gap-3">
                @foreach($branch->socialLinks as $link)
                    <a href="{{ $link->url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 bg-brand-100 hover:bg-brand-200 text-brand-900 px-4 py-2 rounded-full text-sm">
                        <x-icon-social :platform="$link->platform" class="w-4 h-4"/> {{ $link->handle }}
                    </a>
                @endforeach
            </div>
        </div>
        <div class="min-h-[280px]">
            @if($branch->latitude && $branch->longitude)
                <iframe class="w-full h-full min-h-[280px]" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                        src="https://www.openstreetmap.org/export/embed.html?bbox={{ $branch->longitude - 0.004 }}%2C{{ $branch->latitude - 0.003 }}%2C{{ $branch->longitude + 0.004 }}%2C{{ $branch->latitude + 0.003 }}&layer=mapnik&marker={{ $branch->latitude }}%2C{{ $branch->longitude }}"></iframe>
            @endif
        </div>
    </div>
</section>
@endsection
