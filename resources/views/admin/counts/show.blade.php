@extends('admin.layouts.app')
@section('title', 'Inventario #'.$count->id.' · '.$count->branch->name)
@section('actions')<a href="{{ route('admin.inventario.index') }}" class="text-sm text-slate-600 hover:underline">← Inventarios</a>@endsection

@section('content')
@error('count')<p class="mb-4 text-sm text-rose-600">{{ $message }}</p>@enderror
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Contados</p><p class="text-2xl font-bold" id="total-contado">{{ $summary['contados'] }}</p></div>
    <div class="bg-white rounded-xl border border-rose-200 p-4"><p class="text-xs text-rose-700">Faltan</p><p class="text-2xl font-bold text-rose-700">{{ $summary['faltan'] }}</p></div>
    <div class="bg-white rounded-xl border border-sky-200 p-4"><p class="text-xs text-sky-700">Sobran</p><p class="text-2xl font-bold text-sky-700">{{ $summary['sobran'] }}</p></div>
    @can('stock.adjust')<div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Diferencia a costo</p><p class="text-xl font-bold {{ $summary['valor'] < 0 ? 'text-rose-700' : '' }}">{{ money($summary['valor']) }}</p></div>@endcan
</div>

@if($count->status === 'abierto')
    <div x-data="conteo(@js(route('admin.inventario.escanear', $count)))" @scanned="scan($event.detail.code)" class="bg-white rounded-xl border-2 border-amber-300 p-5 mb-5 space-y-3">
        <h3 class="font-semibold">📋 Contando ({{ $count->scope === 'total' ? 'toda la tienda: lo no contado quedará en 0' : 'parcial: solo se ajusta lo contado' }})</h3>
        <p class="text-sm text-slate-500">Escanea <strong>cada par</strong> (uno por uno). Puedes contar desde varios celulares a la vez. Recarga la página para ver las diferencias actualizadas.</p>
        @include('admin.partials.scanner', ['placeholder' => 'Escanea cada par…'])
        <template x-if="last"><p class="text-sm" :class="last.ok ? 'text-emerald-700' : 'text-rose-600'" x-text="last.text"></p></template>
        <ul class="text-sm divide-y divide-slate-100 max-h-48 overflow-y-auto"><template x-for="(h, i) in history" :key="i"><li class="py-1 flex justify-between"><span x-text="h.label"></span><strong x-text="h.counted"></strong></li></template></ul>
    </div>
@endif

<div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-4 py-3">Par</th><th class="px-4 py-3 text-center">Sistema</th><th class="px-4 py-3 text-center">Contado</th><th class="px-4 py-3 text-center">Diferencia</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($rows as $r)
                <tr class="{{ $r['diff'] < 0 ? 'bg-rose-50/60' : ($r['diff'] > 0 ? 'bg-sky-50/60' : '') }}">
                    <td class="px-4 py-2.5"><p class="font-medium">{{ $r['variant']->product->name }} @if($r['variant']->product->code)<span class="text-xs text-slate-400">({{ $r['variant']->product->code }})</span>@endif</p><p class="text-xs text-slate-500">{{ $r['variant']->label() }} · {{ $r['variant']->sku }}</p></td>
                    <td class="px-4 py-2.5 text-center">{{ $r['system'] }}</td>
                    <td class="px-4 py-2.5 text-center font-semibold">{{ $r['counted'] }}</td>
                    <td class="px-4 py-2.5 text-center font-bold {{ $r['diff'] < 0 ? 'text-rose-700' : ($r['diff'] > 0 ? 'text-sky-700' : 'text-emerald-700') }}">{{ $r['diff'] > 0 ? '+' : '' }}{{ $r['diff'] ?: '✔' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Todavía no se contó nada.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($count->status === 'abierto')
    @can('stock.adjust')
        <div class="mt-5 flex flex-wrap gap-3 items-center">
            <form method="POST" action="{{ route('admin.inventario.cerrar', $count) }}" data-once onsubmit="return confirm('¿Cerrar el inventario? El stock del sistema quedará igual a lo contado ({{ $summary['faltan'] }} faltantes, {{ $summary['sobran'] }} sobrantes).')">
                @csrf<button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-lg">✔ Cerrar y ajustar stock</button>
            </form>
            <form method="POST" action="{{ route('admin.inventario.cancelar', $count) }}" data-once onsubmit="return confirm('¿Cancelar este inventario? No se cambia nada.')">
                @csrf<button class="text-sm text-rose-600 hover:underline">Cancelar inventario</button>
            </form>
        </div>
    @endcan
@else
    <p class="mt-5 text-sm text-slate-500">{{ $count->status === 'cerrado' ? 'Cerrado' : 'Cancelado' }} el {{ $count->closed_at?->isoFormat('D MMM YYYY HH:mm') }} por {{ $count->closer?->name }}.</p>
@endif
@endsection

@push('scripts')
<script>
function conteo(url) {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    return {
        last: null, history: [],
        async scan(code) {
            try {
                const r = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ code }) });
                const d = await r.json();
                if (!d.ok) { this.last = { ok: false, text: d.message || 'No se reconoce el código.' }; navigator.vibrate && navigator.vibrate([60, 40, 60]); return; }
                this.last = { ok: true, text: `✔ ${d.label} → ${d.counted}` };
                this.history.unshift({ label: d.label, counted: d.counted });
                const t = document.getElementById('total-contado'); if (t) t.textContent = d.total;
            } catch (e) { this.last = { ok: false, text: 'Sin conexión: vuelve a escanear ese par.' }; }
        },
    };
}
</script>
@endpush
