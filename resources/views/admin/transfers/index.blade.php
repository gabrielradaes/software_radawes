@extends('admin.layouts.app')
@section('title', 'Traspasos entre tiendas')
@section('actions')@can('stock.transfer')<a href="{{ route('admin.traspasos.create') }}" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ Enviar a otra tienda</a>@endcan @endsection

@section('content')
<div class="bg-sky-50 border border-sky-200 text-sky-900 text-sm rounded-xl px-4 py-3 mb-5">
    <strong>Cómo funciona:</strong> la tienda que envía registra los pares (salen de su stock). La tienda que recibe abre el traspaso y toca <em>«Confirmar recepción»</em> cuando llega la caja (entran a su stock). Mientras tanto, figuran <strong>en camino</strong>.
</div>
<div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-4 py-3">Traspaso</th><th class="px-4 py-3">De → A</th><th class="px-4 py-3 text-center">Pares</th><th class="px-4 py-3">Estado</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($transfers as $t)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3"><a href="{{ route('admin.traspasos.show', $t) }}" class="font-semibold hover:text-brand-500">{{ $t->code }}</a><p class="text-xs text-slate-400">{{ $t->created_at->isoFormat('D MMM YYYY HH:mm') }} · {{ $t->sender->name }}</p></td>
                    <td class="px-4 py-3">{{ $t->from->name }} → <strong>{{ $t->to->name }}</strong></td>
                    <td class="px-4 py-3 text-center">{{ $t->pairs }}</td>
                    <td class="px-4 py-3">
                        @if($t->status === 'enviado')
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">🚚 En camino</span>
                            @if($scoped === $t->to_branch_id || ! $scoped)<a href="{{ route('admin.traspasos.show', $t) }}" class="ml-2 text-xs font-semibold text-emerald-700 hover:underline">Confirmar recepción →</a>@endif
                        @elseif($t->status === 'recibido')
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">✔ Recibido</span> <span class="text-xs text-slate-400">{{ $t->received_at->isoFormat('D MMM HH:mm') }} · {{ $t->receiver?->name }}</span>
                        @else
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-200 text-slate-600">Cancelado</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">Aún no hay traspasos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $transfers->links() }}</div>
@endsection
