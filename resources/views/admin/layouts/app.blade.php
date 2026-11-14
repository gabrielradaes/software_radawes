<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · RyE Admin</title>
    @include('partials.head-assets', ['palette' => \App\Support\Theme::defaultPalette()])
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased" x-data="{ side: false }">
@php
    $user = auth()->user();
    $nav = [
        ['Panel', route('admin.dashboard'), 'admin.dashboard', null, '📊'],
        ['Productos', route('admin.productos.index'), 'admin.productos.*', 'products.manage', '👞'],
        ['Categorías y clases', route('admin.categorias.index'), 'admin.categorias.*', 'products.manage', '🗂️'],
        ['Stock', route('admin.stock.index'), 'admin.stock.*', 'stock.view', '📦'],
        ['Publicaciones', route('admin.publicaciones.index'), 'admin.publicaciones.*', 'posts.manage', '📣'],
        ['Comentarios', route('admin.comentarios.index'), 'admin.comentarios.*', 'comments.moderate', '💬'],
        ['Leads WhatsApp', route('admin.leads.index'), 'admin.leads.*', 'leads.manage', '🟢'],
        ['Clientes', route('admin.clientes.index'), 'admin.clientes.*', 'customers.view', '🧑‍🤝‍🧑'],
        ['Usuarios', route('admin.usuarios.index'), 'admin.usuarios.*', 'users.manage', '👥'],
        ['Sucursales', route('admin.sucursales.index'), 'admin.sucursales.*', 'branches.manage', '🏬'],
        ['Colores de la tienda', route('admin.colores.index'), 'admin.colores.*', 'products.manage', '🎨'],
    ];
@endphp

<div class="min-h-screen lg:flex">
    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 w-64 bg-brand-950 text-brand-100 transform transition lg:translate-x-0 lg:static lg:shrink-0" :class="side ? 'translate-x-0' : '-translate-x-full'">
        <div class="h-16 flex items-center gap-3 px-5 border-b border-brand-900">
            <span class="w-9 h-9 rounded-full bg-brand-900 text-gold-400 font-bold flex items-center justify-center ring-2 ring-gold-500/40">RW</span>
            <div class="leading-tight">
                <p class="font-bold text-white">RyE Admin</p>
                <p class="text-[11px] text-brand-300">{{ $user->branch?->name ?? 'Todas las sucursales' }}</p>
            </div>
        </div>
        <nav class="p-3 space-y-0.5">
            @foreach($nav as [$label, $url, $pattern, $perm, $icon])
                @if(! $perm || $user->can($perm))
                    <a href="{{ $url }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs($pattern) ? 'bg-brand-800 text-white' : 'hover:bg-brand-900 text-brand-200' }}">
                        <span class="w-5 text-center">{{ $icon }}</span> {{ $label }}
                    </a>
                @endif
            @endforeach
            <div class="pt-3 mt-3 border-t border-brand-900">
                <a href="{{ route('home') }}" target="_blank" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-brand-200 hover:bg-brand-900"><span class="w-5 text-center">🌐</span> Ver sitio público</a>
            </div>
        </nav>
        <div class="absolute bottom-0 inset-x-0 p-4 border-t border-brand-900">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-full bg-gold-500 text-brand-950 font-bold flex items-center justify-center">{{ $user->initials() }}</span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-white truncate">{{ $user->name }}</p>
                    <p class="text-[11px] text-brand-300">{{ $user->role?->label }}</p>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="text-brand-300 hover:text-white text-xs" title="Salir">Salir</button></form>
            </div>
        </div>
    </aside>
    <div x-show="side" x-cloak @click="side = false" class="fixed inset-0 bg-black/40 z-30 lg:hidden"></div>

    {{-- Contenido --}}
    <div class="flex-1 min-w-0">
        <header class="h-16 bg-white border-b border-slate-200 flex items-center px-4 lg:px-8 gap-4 sticky top-0 z-20">
            <button @click="side = true" class="lg:hidden p-2 -ml-2 rounded hover:bg-slate-100">☰</button>
            <h1 class="font-bold text-lg truncate">@yield('title', 'Panel')</h1>
            <div class="ml-auto flex items-center gap-3">@yield('actions')</div>
        </header>

        <main class="p-4 lg:p-8">
            @if(session('status'))
                <div x-data="{ s: true }" x-show="s" x-init="setTimeout(() => s = false, 5000)" class="mb-5 flex items-center justify-between bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3 rounded-lg">
                    <span>✓ {{ session('status') }}
                        @if($l = session('status_link'))<a href="{{ $l['url'] }}" target="_blank" class="ml-2 font-semibold underline">{{ $l['label'] }}</a>@endif
                    </span><button @click="s = false">✕</button>
                </div>
            @endif
            @if($errors->any())
                <div class="mb-5 bg-rose-50 border border-rose-200 text-rose-800 text-sm px-4 py-3 rounded-lg">
                    <p class="font-semibold mb-1">Revisa estos datos:</p>
                    <ul class="list-disc ml-5 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>

<script>
    function confirmar(msg) { return confirm(msg || '¿Seguro?'); }

    // Evita el doble envío (doble clic en Guardar): tras enviar, el botón queda deshabilitado
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (e.defaultPrevented || form.method.toLowerCase() === 'get' || form.dataset.sending) {
            if (form.dataset.sending) e.preventDefault();
            return;
        }
        form.dataset.sending = '1';
        form.querySelectorAll('button[type=submit], button:not([type])').forEach((b) => {
            b.disabled = true;
            b.classList.add('opacity-60', 'cursor-wait');
            if (b.innerText.trim().length < 40) b.dataset.label = b.innerText, b.innerText = 'Guardando…';
        });
        // Si el navegador vuelve atrás, se reactiva
        window.addEventListener('pageshow', () => { delete form.dataset.sending; form.querySelectorAll('button').forEach((b) => { b.disabled = false; b.classList.remove('opacity-60', 'cursor-wait'); if (b.dataset.label) b.innerText = b.dataset.label; }); }, { once: true });
    });
</script>
@stack('scripts')
</body>
</html>
