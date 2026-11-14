<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar · RyE Admin</title>
    @include('partials.head-assets', ['palette' => \App\Support\Theme::defaultPalette(), 'alpine' => false])
</head>
<body class="min-h-screen bg-brand-950 flex items-center justify-center p-4 font-sans" style="background-image: radial-gradient(circle at 20% 30%, #5a3a2a 0, transparent 45%), radial-gradient(circle at 80% 70%, #8a5a3c 0, transparent 40%);">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <span class="inline-flex w-16 h-16 rounded-full bg-brand-900 text-gold-400 font-display font-bold text-2xl items-center justify-center ring-2 ring-gold-500/40">RW</span>
            <h1 class="font-display text-3xl font-bold text-white mt-4">RyE Admin</h1>
            <p class="text-sm text-white/60 mt-1">Panel de administración</p>
        </div>
        <form method="POST" action="{{ route('admin.login') }}" class="bg-white rounded-2xl shadow-2xl p-7 space-y-4">
            @csrf
            @if($errors->any())
                <p class="bg-rose-50 text-rose-700 text-sm px-3 py-2 rounded-lg">{{ $errors->first() }}</p>
            @endif
            <div>
                <label class="block text-sm font-medium mb-1">Correo</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Contraseña</label>
                <input type="password" name="password" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" class="rounded"> Recordarme</label>
            <button class="w-full bg-brand-900 hover:bg-brand-500 text-white font-semibold py-2.5 rounded-lg transition">Ingresar</button>
        </form>
        <p class="text-center text-xs text-white/40 mt-6"><a href="{{ route('home') }}" class="hover:text-white">← Volver al sitio</a></p>
    </div>
</body>
</html>
