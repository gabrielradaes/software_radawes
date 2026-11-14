<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Ingresar') · RyE Admin</title>
    @include('partials.head-assets', ['palette' => \App\Support\Theme::defaultPalette(), 'alpine' => true])
</head>
<body class="min-h-screen bg-brand-950 flex items-center justify-center p-4 font-sans" style="background-image: radial-gradient(circle at 20% 30%, #5a3a2a 0, transparent 45%), radial-gradient(circle at 80% 70%, #8a5a3c 0, transparent 40%);">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <span class="inline-flex w-16 h-16 rounded-full bg-brand-900 text-gold-400 font-display font-bold text-2xl items-center justify-center ring-2 ring-gold-500/40">RW</span>
            <h1 class="font-display text-3xl font-bold text-white mt-4">@yield('heading', 'RyE Admin')</h1>
            <p class="text-sm text-white/60 mt-1">@yield('subheading', 'Panel de administración')</p>
        </div>
        @if(session('status'))
            <p class="bg-emerald-50 text-emerald-800 text-sm px-3 py-2 rounded-lg mb-4">{{ session('status') }}</p>
        @endif
        @yield('form')
        <p class="text-center text-xs text-white/40 mt-6">@yield('footer')<a href="{{ route('landing') }}" class="hover:text-white">← Volver al sitio</a></p>
    </div>
</body>
</html>
