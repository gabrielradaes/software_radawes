@extends('admin.layouts.app')
@section('title', 'Auditoría')

@section('content')
<form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-5 flex flex-wrap gap-3">
    <select name="usuario" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Todas las personas</option>
        @foreach($users as $u)<option value="{{ $u->id }}" @selected(request('usuario') == $u->id)>{{ $u->name }}</option>@endforeach
    </select>
    <select name="accion" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Todas las acciones</option>
        @foreach($actions as $a)<option value="{{ $a }}" @selected(request('accion') === $a)>{{ ucfirst($a) }}</option>@endforeach
    </select>
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar (producto, venta, usuario…)" class="flex-1 min-w-[200px] rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <button class="bg-slate-800 text-white text-sm px-4 py-2 rounded-lg">Filtrar</button>
</form>
<div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-4 py-3">Cuándo</th><th class="px-4 py-3">Quién</th><th class="px-4 py-3">Qué pasó</th><th class="px-4 py-3">Cambios</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($logs as $l)
                <tr class="align-top">
                    <td class="px-4 py-3 whitespace-nowrap text-xs">{{ $l->created_at->isoFormat('D MMM YYYY HH:mm') }}</td>
                    <td class="px-4 py-3 text-xs">{{ $l->user?->name ?? 'Sistema' }}<br><span class="text-slate-400">{{ $l->ip }}</span></td>
                    <td class="px-4 py-3">{{ $l->description }}</td>
                    <td class="px-4 py-3 text-xs">
                        @foreach($l->changes ?? [] as $field => [$old, $new])
                            <p><span class="text-slate-500">{{ $field }}:</span> <span class="line-through text-rose-600">{{ is_scalar($old) || $old === null ? ($old ?? '—') : json_encode($old) }}</span> → <span class="text-emerald-700">{{ is_scalar($new) || $new === null ? ($new ?? '—') : json_encode($new) }}</span></p>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Sin registros.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
