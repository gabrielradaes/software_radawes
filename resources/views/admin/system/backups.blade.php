@extends('admin.layouts.app')
@section('title', 'Copias de seguridad')
@section('actions')
    <form method="POST" action="{{ route('admin.respaldos.store') }}" data-once>@csrf
        <button class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">💾 Crear copia ahora</button>
    </form>
@endsection

@section('content')
<div class="max-w-3xl space-y-5">
    <div class="bg-sky-50 border border-sky-200 text-sky-900 text-sm rounded-xl p-4 space-y-1">
        <p><strong>Se hace sola:</strong> una copia al día (la primera vez que alguien entra al panel) y otra antes de cada actualización de la página. Se guardan las últimas {{ \App\Services\DatabaseBackup::KEEP }}.</p>
        <p><strong>Recomendado:</strong> descarga una copia cada semana y guárdala en tu Google Drive o en tu computadora (por si el hosting falla).</p>
        <p><strong>Para restaurar:</strong> cPanel → phpMyAdmin → tu base de datos → Importar → elige el archivo <code>.sql.gz</code>.</p>
    </div>
    @error('backup')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-4 py-3">Copia</th><th class="px-4 py-3">Fecha</th><th class="px-4 py-3 text-right">Tamaño</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($backups as $b)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $b['name'] }}</td>
                        <td class="px-4 py-3">{{ $b['at']->isoFormat('ddd D MMM YYYY, HH:mm') }} <span class="text-xs text-slate-400">({{ $b['at']->diffForHumans() }})</span></td>
                        <td class="px-4 py-3 text-right">{{ number_format($b['size'] / 1024, 0, ',', '.') }} KB</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.respaldos.download', $b['name']) }}" class="text-xs font-semibold text-brand-700 hover:underline">⬇ Descargar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Todavía no hay copias. Crea la primera con el botón de arriba.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
