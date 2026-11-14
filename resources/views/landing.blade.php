<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RyE · Nuestras tiendas</title>
    <meta name="description" content="RadaWes, RyE Made in Brazil y RyC: calzado de cuero brasileño, motos y accesorios en La Paz y El Alto.">
    @include('partials.head-assets', ['palette' => \App\Support\Theme::defaultPalette(), 'alpine' => false])
</head>
<body class="min-h-screen bg-[#141010] text-white font-sans antialiased" style="background-image: radial-gradient(circle at 15% 20%, #4a2c1c 0, transparent 40%), radial-gradient(circle at 85% 80%, #0f5132 0, transparent 40%);">
    <header class="max-w-6xl mx-auto px-4 pt-10 pb-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="w-11 h-11 rounded-full bg-white/10 ring-1 ring-white/20 font-display font-bold flex items-center justify-center">RyE</span>
            <div class="leading-tight"><p class="font-display font-bold text-lg">Grupo RyE</p><p class="text-xs text-white/60">Calzado brasileño · motos · accesorios</p></div>
        </div>
        <a href="{{ route('admin.login') }}" class="text-xs text-white/40 hover:text-white">🔐 Acceso equipo</a>
    </header>

    <main class="max-w-6xl mx-auto px-4 pb-20">
        <div class="text-center mb-10">
            <p class="text-xs uppercase tracking-[0.3em] text-white/50 font-semibold">La Paz · El Alto</p>
            <h1 class="font-display text-4xl md:text-6xl font-bold mt-3">Elige tu tienda</h1>
            <p class="text-white/60 mt-3 max-w-xl mx-auto">Cada tienda tiene su propio catálogo, tallas disponibles y novedades. Entra a la más cercana.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            @foreach($branches as $b)
                <a href="{{ route('home', $b) }}" class="group rounded-3xl overflow-hidden bg-white text-slate-900 shadow-2xl ring-1 ring-white/10 hover:-translate-y-1 transition duration-300">
                    <div class="h-40 flex items-center justify-center p-6" style="background: {{ $b->color_surface }}; border-bottom: 6px solid {{ $b->color_accent }};">
                        @if($b->logoUrl())
                            <img src="{{ $b->logoUrl() }}" alt="{{ $b->brandName() }}" class="max-h-28 object-contain">
                        @else
                            <span class="font-display text-5xl font-bold" style="color: {{ $b->color_primary }}">{{ $b->brandName() }}</span>
                        @endif
                    </div>
                    <div class="p-6">
                        <p class="text-[11px] uppercase tracking-[0.2em] font-semibold" style="color: {{ $b->color_accent }}">{{ $b->brand_tagline }}</p>
                        <h2 class="font-display text-2xl font-bold mt-1">{{ $b->brandName() }} <span class="text-slate-400 font-sans text-base font-normal">· {{ $b->city }}</span></h2>
                        <p class="text-sm text-slate-600 mt-2">{{ $b->tagline }}</p>
                        <p class="text-xs text-slate-500 mt-3">📍 {{ $b->address }}</p>
                        <div class="mt-5 flex items-center justify-between">
                            <span class="text-xs text-slate-500">{{ $b->products_count }} productos</span>
                            <span class="inline-flex items-center gap-1 font-semibold text-sm px-4 py-2 rounded-full text-white group-hover:opacity-90" style="background: {{ $b->color_primary }}">Entrar →</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        @if($latest->count())
            <h2 class="font-display text-2xl font-bold mt-16 mb-5">Últimas novedades</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($latest as $p)
                    <a href="{{ route('feed.show', ['branch' => $p->branch, 'post' => $p]) }}" class="flex gap-3 bg-white/5 hover:bg-white/10 rounded-2xl p-3 ring-1 ring-white/10">
                        <div class="w-20 h-20 rounded-xl overflow-hidden bg-white/10 shrink-0 flex items-center justify-center">
                            @if($img = $p->media->firstWhere('type', 'imagen'))<img src="{{ $img->url() }}" class="w-full h-full object-cover">
                            @elseif($p->media->firstWhere('type', 'video'))<span class="text-2xl">▶</span>@endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] uppercase tracking-wider font-semibold" style="color: {{ $p->branch->color_accent }}">{{ $p->branch->brandName() }} · {{ $p->branch->city }}</p>
                            <p class="font-semibold leading-snug line-clamp-2">{{ $p->title }}</p>
                            <p class="text-xs text-white/50 mt-1">{{ $p->published_at->diffForHumans() }} · ♥ {{ $p->likes_count }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </main>
    <footer class="text-center text-xs text-white/30 pb-8">© {{ date('Y') }} Grupo RyE · Bolivia</footer>
</body>
</html>
