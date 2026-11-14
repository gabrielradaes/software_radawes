@php
    $theme = \App\Support\Theme::resolve($currentBranch);
    $palette = $theme['palette'];
    $canTheme = \App\Support\Theme::canEdit($currentBranch);
    $otherStores = $allBranches->where('id', '!=', $currentBranch->id);
@endphp
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $currentBranch->brandName().' · '.$currentBranch->city)</title>
    <meta name="description" content="@yield('meta_description', $currentBranch->tagline)">
    <meta name="theme-color" content="{{ $currentBranch->color_primary }}">
    @include('partials.head-assets', ['palette' => $palette, 'collapse' => true])
    @stack('head')
</head>
<body class="bg-brand-50 text-brand-900 font-sans antialiased">

{{-- Barra superior (clara) --}}
<div class="bg-brand-100 text-brand-800 text-xs border-b border-brand-200">
    <div class="max-w-7xl mx-auto px-4 h-9 flex items-center justify-between gap-4">
        <p class="truncate">
            <span class="hidden sm:inline">📍 {{ $currentBranch->address }}, {{ $currentBranch->city }}</span>
            <span class="sm:hidden">📍 {{ $currentBranch->city }}</span>
            @if($currentBranch->opening_hours) <span class="hidden md:inline text-brand-500"> · {{ $currentBranch->opening_hours }}</span> @endif
        </p>
        <div class="flex items-center gap-4 shrink-0">
            @if($otherStores->count())
                <div class="hidden md:flex items-center gap-2 text-brand-500">
                    <span>Otras tiendas:</span>
                    @foreach($otherStores as $b)
                        <a href="{{ route('home', $b) }}" class="font-semibold text-brand-800 hover:text-brand-500 underline-offset-2 hover:underline">{{ $b->brandName() }} {{ $b->city }}</a>@if(! $loop->last)<span>·</span>@endif
                    @endforeach
                </div>
            @endif
            <a href="{{ route('whatsapp', ['origen' => 'barra']) }}" target="_blank" rel="noopener" class="font-semibold text-wa-600 hover:text-brand-900">
                WhatsApp +{{ $currentBranch->whatsapp }}
            </a>
        </div>
    </div>
</div>

{{-- Navegación --}}
<header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-brand-100 shadow-sm" x-data="{ open: false }">
    <div class="max-w-7xl mx-auto px-4 h-20 flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="flex items-center gap-3 shrink-0">
            @if($currentBranch->logoUrl())
                <img src="{{ $currentBranch->logoUrl() }}" alt="{{ $currentBranch->brandName() }}" class="h-14 w-auto object-contain">
            @else
                <span class="w-12 h-12 rounded-full bg-brand-900 text-gold-400 font-display font-bold text-lg flex items-center justify-center ring-2 ring-gold-500/40">{{ mb_substr($currentBranch->brandName(), 0, 3) }}</span>
            @endif
            <span class="leading-tight">
                <span class="block font-display font-bold text-xl text-brand-900">{{ $currentBranch->brandName() }}</span>
                <span class="block text-[11px] uppercase tracking-[0.2em] text-brand-500">{{ $currentBranch->brand_tagline }}</span>
                @if($currentBranch->brand_small)<span class="block text-[10px] text-brand-300 tracking-wider">{{ $currentBranch->brand_small }} · {{ $currentBranch->city }}</span>@endif
            </span>
        </a>

        <nav class="hidden lg:flex items-center gap-7 text-sm font-medium">
            <a href="{{ route('home') }}" class="hover:text-brand-500 {{ request()->routeIs('home') ? 'text-brand-500' : '' }}">Inicio</a>
            @foreach($rootCategories as $cat)
                <div class="relative" x-data="{ o: false }" @mouseenter="o = true" @mouseleave="o = false">
                    <a href="{{ route('catalog.category', $cat) }}" class="hover:text-brand-500 inline-flex items-center gap-1 {{ request()->is('*/catalogo/'.$cat->slug) ? 'text-brand-500' : '' }}">
                        {{ $cat->name }}
                        @if($cat->children->count()) <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg> @endif
                    </a>
                    @if($cat->children->count())
                        <div x-show="o" x-cloak x-transition class="absolute left-0 top-full pt-3 w-56">
                            <div class="bg-white rounded-xl shadow-xl border border-brand-100 py-2">
                                @foreach($cat->children as $child)
                                    <a href="{{ route('catalog.category', $child) }}" class="block px-4 py-2 text-sm hover:bg-brand-50 hover:text-brand-500">{{ $child->name }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
            <a href="{{ route('feed.index') }}" class="hover:text-brand-500 {{ request()->routeIs('feed.*') ? 'text-brand-500' : '' }}">Novedades</a>
            <a href="{{ route('landing') }}" class="hover:text-brand-500">Tiendas</a>
        </nav>

        <div class="flex items-center gap-2">
            <form action="{{ route('catalog.index') }}" method="GET" class="hidden md:flex items-center">
                <div class="relative">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar modelo..." class="w-44 lg:w-52 rounded-full border border-brand-200 bg-brand-50 pl-9 pr-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300 focus:bg-white">
                    <svg class="w-4 h-4 absolute left-3 top-2 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                </div>
            </form>
            <a href="{{ route('whatsapp', ['origen' => 'cabecera']) }}" target="_blank" rel="noopener" class="hidden sm:inline-flex items-center gap-2 bg-wa-500 hover:bg-wa-600 text-white text-sm font-semibold px-4 py-2 rounded-full transition">
                <x-icon-whatsapp class="w-4 h-4"/> Pedir
            </a>
            @if($customer = auth('customer')->user())
                <div class="relative" x-data="{ u: false }" @click.outside="u = false">
                    <button @click="u = !u" class="flex items-center gap-2 pl-1 pr-3 py-1 rounded-full border border-brand-200 hover:bg-brand-50 text-sm font-medium" title="Mi cuenta">
                        <span class="w-8 h-8 rounded-full bg-brand-900 text-gold-400 font-bold flex items-center justify-center text-xs">{{ $customer->initials() }}</span>
                        <span class="hidden md:inline">{{ $customer->firstName() }}</span>
                    </button>
                    <div x-show="u" x-cloak x-transition class="absolute right-0 top-full mt-2 w-52 bg-white rounded-xl shadow-xl border border-brand-100 py-2 text-sm">
                        <a href="{{ route('customer.account') }}" class="block px-4 py-2 hover:bg-brand-50">⚙️ Mi cuenta</a>
                        <a href="{{ route('customer.account', ['tab' => 'favoritos']) }}" class="block px-4 py-2 hover:bg-brand-50">❤️ Mis favoritos</a>
                        <a href="{{ route('customer.account', ['tab' => 'consultas']) }}" class="block px-4 py-2 hover:bg-brand-50">📱 Mis consultas</a>
                        <form method="POST" action="{{ route('customer.logout') }}" class="border-t border-brand-100 mt-1 pt-1">@csrf<button class="w-full text-left px-4 py-2 hover:bg-brand-50 text-brand-500">Cerrar sesión</button></form>
                    </div>
                </div>
            @else
                <a href="{{ route('customer.login') }}" class="hidden sm:inline-flex items-center gap-1.5 text-sm font-semibold text-brand-900 hover:text-brand-500 px-2" title="Iniciar sesión">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Ingresar
                </a>
            @endif
            <button @click="open = !open" class="lg:hidden p-2 rounded-lg hover:bg-brand-50" aria-label="Menú">
                <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="open" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    {{-- Menú móvil --}}
    <div x-show="open" x-cloak x-transition class="lg:hidden border-t border-brand-100 bg-white">
        <div class="px-4 py-4 space-y-1">
            <form action="{{ route('catalog.index') }}" method="GET" class="mb-3">
                <input type="search" name="q" placeholder="Buscar modelo..." class="w-full rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm">
            </form>
            <a href="{{ route('home') }}" class="block px-3 py-2 rounded-lg hover:bg-brand-50 font-medium">Inicio</a>
            @foreach($rootCategories as $cat)
                <a href="{{ route('catalog.category', $cat) }}" class="block px-3 py-2 rounded-lg hover:bg-brand-50 font-medium">{{ $cat->name }}</a>
                @foreach($cat->children as $child)
                    <a href="{{ route('catalog.category', $child) }}" class="block pl-8 pr-3 py-1.5 rounded-lg hover:bg-brand-50 text-sm text-brand-700">{{ $child->name }}</a>
                @endforeach
            @endforeach
            <a href="{{ route('feed.index') }}" class="block px-3 py-2 rounded-lg hover:bg-brand-50 font-medium">Novedades</a>
            <div class="border-t border-brand-100 mt-2 pt-2">
                <p class="px-3 py-1 text-[11px] uppercase tracking-wider text-brand-500">Otras tiendas</p>
                @foreach($otherStores as $b)
                    <a href="{{ route('home', $b) }}" class="block px-3 py-2 rounded-lg hover:bg-brand-50 text-sm">{{ $b->brandName() }} · {{ $b->city }}</a>
                @endforeach
            </div>
            <div class="border-t border-brand-100 mt-2 pt-2">
                @if(auth('customer')->check())
                    <a href="{{ route('customer.account') }}" class="block px-3 py-2 rounded-lg hover:bg-brand-50 font-medium">⚙️ Mi cuenta</a>
                    <form method="POST" action="{{ route('customer.logout') }}">@csrf<button class="w-full text-left px-3 py-2 rounded-lg hover:bg-brand-50 text-brand-500">Cerrar sesión</button></form>
                @else
                    <a href="{{ route('customer.login') }}" class="block px-3 py-2 rounded-lg hover:bg-brand-50 font-medium">👤 Ingresar</a>
                    <a href="{{ route('customer.register') }}" class="block px-3 py-2 rounded-lg hover:bg-brand-50 font-medium text-brand-500">Crear cuenta</a>
                @endif
            </div>
        </div>
    </div>
</header>

@if(session('status'))
    <div x-data="{ s: true }" x-show="s" x-init="setTimeout(() => s = false, 4000)" class="fixed top-24 left-1/2 -translate-x-1/2 z-50 bg-brand-900 text-white text-sm px-4 py-2 rounded-full shadow-lg">
        {{ session('status') }}
    </div>
@endif

<main class="min-h-[60vh]">
    @yield('content')
</main>

{{-- Pie --}}
<footer class="mt-20 bg-brand-100 text-brand-800 border-t border-brand-200">
    <div class="max-w-7xl mx-auto px-4 py-14 grid gap-10 md:grid-cols-4">
        <div class="md:col-span-2">
            @if($currentBranch->logoUrl())
                <img src="{{ $currentBranch->logoUrl() }}" alt="{{ $currentBranch->brandName() }}" class="h-16 w-auto object-contain mb-3">
            @endif
            <p class="font-display text-2xl text-brand-900 font-bold">{{ $currentBranch->name }}</p>
            <p class="mt-2 text-sm text-brand-700 max-w-md">{{ $currentBranch->description }}</p>
            <div class="mt-5 flex gap-3">
                @foreach($currentBranch->socialLinks as $link)
                    <a href="{{ $link->url }}" target="_blank" rel="noopener" title="{{ $link->handle }}" class="w-10 h-10 rounded-full bg-white hover:bg-brand-200 border border-brand-200 flex items-center justify-center text-brand-900">
                        <x-icon-social :platform="$link->platform" class="w-5 h-5"/>
                    </a>
                @endforeach
            </div>
        </div>
        <div>
            <p class="font-semibold text-brand-900 mb-3">Catálogo</p>
            <ul class="space-y-2 text-sm">
                @foreach($rootCategories as $cat)
                    <li><a href="{{ route('catalog.category', $cat) }}" class="hover:text-brand-500">{{ $cat->name }}</a></li>
                @endforeach
                <li><a href="{{ route('catalog.index', ['oferta' => 1]) }}" class="hover:text-brand-500 text-gold-600 font-semibold">Ofertas</a></li>
            </ul>
            <p class="font-semibold text-brand-900 mb-3 mt-6">Tiendas</p>
            <ul class="space-y-2 text-sm">
                @foreach($allBranches as $b)
                    <li><a href="{{ route('home', $b) }}" class="hover:text-brand-500 {{ $b->id === $currentBranch->id ? 'text-brand-900 font-semibold' : '' }}">{{ $b->brandName() }} · {{ $b->city }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <p class="font-semibold text-brand-900 mb-3">Visítanos</p>
            <ul class="space-y-2 text-sm">
                <li>{{ $currentBranch->address }}</li>
                <li>{{ $currentBranch->city }}, {{ $currentBranch->country }}</li>
                @if($currentBranch->opening_hours)<li>{{ $currentBranch->opening_hours }}</li>@endif
                <li><a href="{{ route('whatsapp', ['origen' => 'pie']) }}" target="_blank" rel="noopener" class="text-wa-600 hover:text-brand-900 font-semibold">+{{ $currentBranch->whatsapp }}</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-brand-200">
        <div class="max-w-7xl mx-auto px-4 py-5 text-xs text-brand-500 flex flex-col sm:flex-row justify-between gap-2">
            <span>© {{ date('Y') }} Grupo RyE · {{ $currentBranch->name }}. Todos los derechos reservados.</span>
            <span class="flex items-center gap-3">
                <a href="{{ auth('web')->check() ? route('admin.dashboard') : route('admin.login') }}" class="hover:text-brand-500">🔐 Acceso equipo</a>
                <span>·</span>
                <span>Hecho con Laravel</span>
            </span>
        </div>
    </div>
</footer>

{{-- Selector de paleta: solo lo ve el equipo con permiso; el cliente nunca lo ve --}}
@if($canTheme)
    @php
        $total = \App\Support\Palettes::count();
        $shown = $theme['preview'] ?? $theme['current'] ?? 1;
        $prev = $shown <= 1 ? $total : $shown - 1;
        $next = $shown >= $total ? 1 : $shown + 1;
        $pal = \App\Support\Palettes::get($shown);
        $isSaved = ! $theme['preview'] || $theme['preview'] === $theme['current'];
    @endphp
    <div class="fixed bottom-5 left-5 z-50 bg-white/95 backdrop-blur rounded-2xl shadow-2xl border border-brand-200 p-3 w-72 text-brand-900" x-data="{ open: true }">
        <div class="flex items-center justify-between">
            <p class="text-[11px] uppercase tracking-wider font-semibold text-brand-500">🎨 Paleta de la tienda <span class="text-brand-300">(solo equipo)</span></p>
            <button @click="open = !open" class="text-brand-500 text-xs" x-text="open ? '—' : '+'"></button>
        </div>
        <div x-show="open" x-collapse>
            <div class="mt-2 flex items-center gap-2">
                <a href="{{ request()->fullUrlWithQuery(['paleta' => $prev]) }}" class="w-9 h-9 rounded-full border border-brand-200 hover:bg-brand-50 flex items-center justify-center font-bold" title="Anterior">‹</a>
                <div class="flex-1 text-center">
                    <p class="font-display font-bold text-lg leading-none">{{ $shown }} <span class="text-brand-300 text-sm font-sans font-normal">/ {{ $total }}</span></p>
                    <p class="text-xs text-brand-700">{{ $pal['name'] ?? 'Personalizada' }}</p>
                </div>
                <a href="{{ request()->fullUrlWithQuery(['paleta' => $next]) }}" class="w-9 h-9 rounded-full border border-brand-200 hover:bg-brand-50 flex items-center justify-center font-bold" title="Siguiente">›</a>
            </div>
            @if($pal)
                <div class="mt-2 flex gap-1.5 justify-center">
                    <span class="w-7 h-7 rounded-full ring-1 ring-black/10" style="background: {{ $pal['primary'] }}" title="Primario"></span>
                    <span class="w-7 h-7 rounded-full ring-1 ring-black/10" style="background: {{ $pal['accent'] }}" title="Acento"></span>
                    <span class="w-7 h-7 rounded-full ring-1 ring-black/10" style="background: {{ $pal['surface'] }}" title="Fondo"></span>
                </div>
            @endif
            <div class="mt-3 flex gap-2">
                @if($isSaved)
                    <p class="flex-1 text-center text-xs text-emerald-700 bg-emerald-50 rounded-lg py-2">✓ Esta es la que ven los clientes</p>
                @else
                    <form method="POST" action="{{ route('theme.save') }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="paleta" value="{{ $shown }}">
                        <button class="w-full bg-brand-900 hover:bg-brand-700 text-white text-sm font-semibold py-2 rounded-lg">Guardar para {{ $currentBranch->brandName() }}</button>
                    </form>
                    <a href="{{ request()->fullUrlWithQuery(['paleta' => null]) }}" class="px-3 py-2 text-xs text-brand-500 hover:text-brand-900 border border-brand-200 rounded-lg">Cancelar</a>
                @endif
            </div>
            <p class="mt-2 text-[10px] text-brand-400 text-center">Vista previa solo para ti hasta que guardes.</p>
        </div>
    </div>
@endif

{{-- Botón flotante de WhatsApp --}}
<a href="{{ route('whatsapp', ['origen' => 'flotante']) }}" target="_blank" rel="noopener"
   class="fixed bottom-5 right-5 z-50 w-14 h-14 rounded-full bg-wa-500 hover:bg-wa-600 text-white shadow-xl flex items-center justify-center transition hover:scale-105"
   aria-label="Escríbenos por WhatsApp">
    <x-icon-whatsapp class="w-7 h-7"/>
    <span class="absolute inline-flex h-full w-full rounded-full bg-wa-500 opacity-40 animate-ping -z-10"></span>
</a>

<script>
    window.RyE = {
        csrf: document.querySelector('meta[name="csrf-token"]').content,
        base: @js(url('/'.$currentBranch->slug)),
        async toggleLike(type, id) {
            const r = await fetch(`${this.base}/me-gusta/${type}/${id}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
            });
            return r.json();
        },
    };
</script>
@stack('scripts')
</body>
</html>
