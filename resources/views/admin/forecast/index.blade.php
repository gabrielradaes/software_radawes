@extends('admin.layouts.app')
@section('title', 'Pronóstico de ventas')

@section('content')
@php
    $bars = collect($series)->map(fn ($m, $ym) => ['label' => \Illuminate\Support\Carbon::createFromFormat('Y-m', $ym)->isoFormat('MMM YY'), 'value' => $m['pares'], 'kind' => 'real'])
        ->values()->merge(collect($forecast)->map(fn ($f, $ym) => ['label' => \Illuminate\Support\Carbon::createFromFormat('Y-m', $ym)->isoFormat('MMM YY'), 'value' => $f['pares'], 'kind' => 'pronostico', 'low' => $f['low'], 'high' => $f['high']])->values());
    $max = max(1, $bars->max(fn ($b) => $b['high'] ?? $b['value']));
    $nextF = $forecast[$nextMonth->format('Y-m')];
@endphp

@unless($scoped)
    <form method="GET" class="mb-5 flex items-center gap-2 text-sm">
        <span class="text-slate-500">Ver:</span>
        <select name="sucursal" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Todas las tiendas</option>
            @foreach($branches as $b)<option value="{{ $b->id }}" @selected($branchId === $b->id)>{{ $b->name }}</option>@endforeach
        </select>
    </form>
@endunless

@if($history < 3)
    <div class="mb-5 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl px-4 py-3 text-sm">
        <strong>Todavía hay pocas ventas registradas ({{ $history }} {{ $history === 1 ? 'mes' : 'meses' }}).</strong> Por ahora el pronóstico se apoya en el comportamiento típico de una zapatería en Bolivia (Navidad, Día del Padre, Día de la Madre, clases…). Mientras más ventas registren, más preciso será: con 1 año ya aprende la temporada de ustedes, con 2 años es lo más confiable.
    </div>
@endif

{{-- Cifras clave --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Este mes (hasta hoy)</p>
        <p class="text-2xl font-bold">{{ $currentMonth['pares'] }} <span class="text-sm font-normal text-slate-500">pares</span></p>
        <p class="text-xs text-slate-500">Esperado en el mes: ~{{ $forecast[now()->format('Y-m')]['pares'] }}</p>
    </div>
    <div class="bg-white rounded-xl border-2 border-brand-300 p-4">
        <p class="text-xs text-brand-700 font-semibold">Próximo mes · {{ $nextF['label'] }}</p>
        <p class="text-2xl font-bold">~{{ $nextF['pares'] }} <span class="text-sm font-normal text-slate-500">pares</span></p>
        <p class="text-xs text-slate-500">entre {{ $nextF['low'] }} y {{ $nextF['high'] }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Tendencia</p>
        <p class="text-2xl font-bold {{ $trend > 0.02 ? 'text-emerald-700' : ($trend < -0.02 ? 'text-rose-700' : '') }}">{{ $trend > 0.02 ? '▲' : ($trend < -0.02 ? '▼' : '●') }} {{ $trend > 0 ? '+' : '' }}{{ round($trend * 100) }}%</p>
        <p class="text-xs text-slate-500">últimos 6 meses vs. los 6 anteriores</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Confianza del pronóstico</p>
        <p class="text-2xl font-bold">{{ ucfirst($confidence) }}</p>
        <p class="text-xs text-slate-500">{{ $history }} meses con ventas</p>
    </div>
</div>

{{-- Gráfico: pares por mes (real y pronóstico) --}}
<div class="bg-white rounded-xl border border-slate-200 p-5 mb-6" x-data="{ tip: null, table: false }">
    <div class="flex flex-wrap justify-between gap-2 mb-1">
        <h3 class="font-semibold">Pares vendidos por mes · {{ $branchName }}</h3>
        <div class="flex items-center gap-4 text-xs text-slate-600">
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background: rgb(var(--brand-700))"></span> Vendido</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm forecast-fill"></span> Pronóstico</span>
            <button type="button" @click="table = !table" class="font-semibold text-brand-700 hover:underline" x-text="table ? 'Ver gráfico' : 'Ver tabla'"></button>
        </div>
    </div>
    <div x-show="!table" class="relative overflow-x-auto"><div class="min-w-[560px]">
        <div class="flex items-end gap-1.5 h-56 border-b border-slate-200 pt-6">
            @foreach($bars as $i => $b)
                <div class="flex-1 h-full flex flex-col justify-end items-center relative group"
                     @mouseenter="tip = {{ $i }}" @mouseleave="tip = null" @focusin="tip = {{ $i }}" @focusout="tip = null" tabindex="0">
                    @if($b['kind'] === 'pronostico')
                        <span class="absolute bg-brand-100/70 w-full rounded-t" style="bottom: 0; height: {{ round(($b['high'] / $max) * 100, 1) }}%"></span>
                    @endif
                    <span class="relative w-full max-w-10 rounded-t {{ $b['kind'] === 'pronostico' ? 'forecast-fill' : '' }}"
                          style="height: {{ max(1, round(($b['value'] / $max) * 100, 1)) }}%; {{ $b['kind'] === 'real' ? 'background: rgb(var(--brand-700))' : '' }}"></span>
                    <div x-show="tip === {{ $i }}" x-cloak class="absolute bottom-full mb-1 z-10 whitespace-nowrap bg-slate-900 text-white text-xs rounded-lg px-2.5 py-1.5 shadow-lg">
                        <strong>{{ $b['label'] }}</strong> · {{ $b['kind'] === 'real' ? $b['value'].' pares vendidos' : '~'.$b['value'].' pares (entre '.$b['low'].' y '.$b['high'].')' }}
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex gap-1.5 mt-1">
            @foreach($bars as $b)<span class="flex-1 text-center text-[10px] {{ $b['kind'] === 'pronostico' ? 'text-brand-700 font-semibold' : 'text-slate-500' }}">{{ $b['label'] }}</span>@endforeach
        </div>
    </div></div>
    <table x-show="table" x-cloak class="w-full text-sm">
        <thead class="text-xs uppercase text-slate-500 text-left"><tr><th class="py-1">Mes</th><th>Tipo</th><th class="text-right">Pares</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @foreach($bars as $b)<tr><td class="py-1">{{ $b['label'] }}</td><td>{{ $b['kind'] === 'real' ? 'Vendido' : 'Pronóstico' }}</td><td class="text-right">{{ $b['kind'] === 'real' ? $b['value'] : '~'.$b['value'].' ('.$b['low'].'–'.$b['high'].')' }}</td></tr>@endforeach
        </tbody>
    </table>
</div>

<div class="grid lg:grid-cols-3 gap-6 mb-6">
    {{-- Fechas que vienen --}}
    <div class="bg-white rounded-xl border border-slate-200">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold">📅 Fechas que vienen (90 días)</div>
        <ul class="divide-y divide-slate-100">
            @forelse($events as $e)
                <li class="px-5 py-3 text-sm"><p class="font-medium">{{ $e['name'] }} <span class="text-xs text-slate-500 font-normal">· {{ $e['date']->isoFormat('ddd D MMM') }} (en {{ (int) today()->diffInDays($e['date']) }} días)</span></p><p class="text-slate-600">{{ $e['tip'] }}</p></li>
            @empty<li class="px-5 py-4 text-sm text-slate-400">Sin fechas especiales en los próximos 90 días.</li>@endforelse
        </ul>
        <div class="px-5 py-3 border-t border-slate-100 text-sm bg-sky-50/60">
            <p class="font-medium">{{ $weather['icon'] }} {{ $nextMonth->isoFormat('MMMM') }}: {{ $weather['name'] }}</p>
            <p class="text-slate-600">{{ $weather['tip'] }}</p>
        </div>
    </div>

    {{-- Por clase --}}
    <div class="bg-white rounded-xl border border-slate-200">
        <div class="px-5 py-3 border-b border-slate-100"><p class="font-semibold">👞 Qué se venderá en {{ $nextMonth->isoFormat('MMMM') }}</p><p class="text-xs text-slate-500">según {{ $byClass['basis'] }}</p></div>
        <ul class="divide-y divide-slate-100">
            @forelse($byClass['rows'] as $c)
                <li class="px-5 py-2.5 text-sm">
                    <div class="flex justify-between"><span>{{ $c['name'] }}</span><strong>~{{ $c['expected'] }} pares</strong></div>
                    <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-full rounded-full" style="width: {{ round($c['share'] * 100) }}%; background: rgb(var(--brand-600))"></div></div>
                </li>
            @empty<li class="px-5 py-4 text-sm text-slate-400">Aún no hay ventas suficientes por clase.</li>@endforelse
        </ul>
    </div>

    {{-- Modelos --}}
    <div class="bg-white rounded-xl border border-slate-200">
        <div class="px-5 py-3 border-b border-slate-100"><p class="font-semibold">🏆 Modelos que más se venden en esa época</p><p class="text-xs text-slate-500">según {{ $topModels['basis'] }}</p></div>
        <ol class="divide-y divide-slate-100">
            @forelse($topModels['rows'] as $i => $m)
                <li class="px-5 py-2 text-sm flex justify-between gap-2"><span class="truncate">{{ $i + 1 }}. {{ $m->name }} @if($m->code)<span class="text-xs text-slate-400">({{ $m->code }})</span>@endif</span><span class="whitespace-nowrap text-slate-600">{{ $m->pares }} pares</span></li>
            @empty<li class="px-5 py-4 text-sm text-slate-400">Aún no hay ventas registradas.</li>@endforelse
        </ol>
    </div>
</div>

{{-- Compra sugerida por talla --}}
<div class="bg-white rounded-xl border border-slate-200 overflow-x-auto mb-6">
    <div class="px-5 py-3 border-b border-slate-100"><p class="font-semibold">🛒 Cuántos pares pedir por talla para {{ $nextMonth->isoFormat('MMMM') }}</p>
        <p class="text-xs text-slate-500">Pares esperados repartidos según las tallas que más se vendieron en el último año, menos lo que ya hay disponible.</p></div>
    @if($buy)
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-2 text-left">Talla</th><th class="px-4 py-2 text-right">% de ventas</th><th class="px-4 py-2 text-right">Se venderán ~</th><th class="px-4 py-2 text-right">Hay disponibles</th><th class="px-4 py-2 text-right">Pedir</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($buy as $r)
                    <tr><td class="px-4 py-2 font-semibold">{{ $r['talla'] }}</td><td class="px-4 py-2 text-right">{{ round($r['share'] * 100) }}%</td><td class="px-4 py-2 text-right">{{ $r['expected'] }}</td><td class="px-4 py-2 text-right">{{ $r['stock'] }}</td>
                        <td class="px-4 py-2 text-right font-bold {{ $r['buy'] ? 'text-emerald-700' : 'text-slate-400' }}">{{ $r['buy'] ?: '—' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="px-5 py-4 text-sm text-slate-400">Cuando haya ventas con talla registradas aparecerá la sugerencia de compra.</p>
    @endif
</div>

<p class="text-xs text-slate-400">El pronóstico es una estimación con el historial de ventas: úsalo como guía, no como certeza. Se actualiza cada 30 minutos.</p>

<style>
    .forecast-fill { background: repeating-linear-gradient(135deg, rgb(var(--brand-400)) 0 3px, rgb(var(--brand-200)) 3px 6px); }
</style>
@endsection
