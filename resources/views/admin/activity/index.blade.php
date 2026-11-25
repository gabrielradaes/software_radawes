@extends('admin.layouts.app')
@section('title', $mine ? 'Mi actividad' : 'Historial de actividad')

@section('content')
<p class="text-sm text-slate-600 mb-4 max-w-3xl">
    @if($mine)
        Todo lo que hiciste en el sistema, del más reciente al más antiguo: ventas, publicaciones, cambios de stock, inicios de sesión…
    @elseif(auth()->user()->isAdmin())
        Qué hizo cada persona del equipo y cada cliente en la página. Se anota solo, no hay que hacer nada.
    @else
        Lo que hizo el equipo de tu tienda y los clientes en su página. Se anota solo.
    @endif
</p>

@if(! $mine && count($summary))
    <div class="mb-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Hoy</p>
        <div class="flex flex-wrap gap-2">
            @foreach($summary as $s)
                <a href="{{ route('admin.auditoria', ['usuario' => $s['user']->id, 'desde' => today()->toDateString()]) }}" class="flex items-center gap-2 bg-white border border-slate-200 hover:border-brand-500 rounded-full pl-1 pr-3 py-1 text-sm">
                    <span class="w-7 h-7 rounded-full bg-brand-900 text-white text-xs font-bold flex items-center justify-center">{{ $s['user']->initials() }}</span>
                    <span class="font-medium">{{ $s['user']->firstName() }}</span>
                    <span class="text-slate-500">{{ $s['n'] }} {{ $s['n'] === 1 ? 'acción' : 'acciones' }} · {{ $s['last']->format('H:i') }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endif

<form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-5 grid sm:grid-cols-2 lg:grid-cols-6 gap-3 text-sm">
    @unless($mine)
        <select name="usuario" class="rounded-lg border border-slate-300 px-3 py-2">
            <option value="">Todas las personas</option>
            @foreach($people as $u)<option value="{{ $u->id }}" @selected(request('usuario') == $u->id)>{{ $u->name }}</option>@endforeach
        </select>
        <select name="quien" class="rounded-lg border border-slate-300 px-3 py-2">
            <option value="">Equipo y clientes</option>
            <option value="equipo" @selected(request('quien') === 'equipo')>Solo el equipo</option>
            <option value="clientes" @selected(request('quien') === 'clientes')>Solo clientes de la página</option>
        </select>
        @if($branches->count())
            <select name="sucursal" class="rounded-lg border border-slate-300 px-3 py-2">
                <option value="">Todas las tiendas</option>
                @foreach($branches as $b)<option value="{{ $b->id }}" @selected(request('sucursal') == $b->id)>{{ $b->name }}</option>@endforeach
            </select>
        @endif
    @endunless
    <label class="flex items-center gap-2"><span class="text-slate-500 shrink-0">Desde</span><input type="date" name="desde" value="{{ request('desde') }}" class="w-full rounded-lg border border-slate-300 px-2 py-2"></label>
    <label class="flex items-center gap-2"><span class="text-slate-500 shrink-0">Hasta</span><input type="date" name="hasta" value="{{ request('hasta') }}" class="w-full rounded-lg border border-slate-300 px-2 py-2"></label>
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar: venta, producto, nombre…" class="rounded-lg border border-slate-300 px-3 py-2 {{ $mine ? 'lg:col-span-3' : '' }}">
    <div class="flex gap-2 lg:col-span-6 sm:col-span-2">
        <button class="bg-brand-900 text-white px-5 py-2 rounded-lg font-semibold">Filtrar</button>
        @if(request()->except('page'))<a href="{{ url()->current() }}" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Quitar filtros</a>@endif
    </div>
</form>

@php $lastDay = null; @endphp
<div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
    @forelse($groups as $g)
        @php $l = $g['main']; $day = $l->created_at->isoFormat('dddd D [de] MMMM YYYY'); @endphp
        @if($day !== $lastDay)
            <div class="px-4 py-2 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 sticky top-16 z-10">{{ $l->created_at->isToday() ? 'Hoy' : ($l->created_at->isYesterday() ? 'Ayer' : $day) }}</div>
            @php $lastDay = $day; @endphp
        @endif
        <div class="px-4 py-3 flex gap-3 {{ $l->action === 'ingreso_fallido' ? 'bg-rose-50' : '' }}">
            <span class="w-8 h-8 shrink-0 rounded-full bg-slate-100 flex items-center justify-center text-base">{{ $l->icon() }}</span>
            <div class="flex-1 min-w-0">
                <p class="text-sm text-slate-800 break-words">{{ $l->description }}</p>
                <p class="text-xs text-slate-500 mt-0.5">
                    <span class="font-medium text-slate-700">{{ $l->created_at->format('H:i') }}</span>
                    @unless($mine)
                        · {{ $l->who() }}
                        @if($l->user?->role)<span class="ml-1 px-1.5 py-0.5 rounded bg-slate-100 text-[10px] uppercase tracking-wide">{{ $l->user->role->label }}</span>@endif
                        @if($l->customer)<a href="{{ route('admin.clientes.show', $l->customer) }}" class="ml-1 text-brand-500 hover:underline">ver cliente</a>@endif
                    @endunless
                    @if($l->branch) · {{ $l->branch->name }}@endif
                </p>
                @foreach($g['details'] as $d)
                    <div class="mt-2 border-l-2 border-slate-200 pl-3 text-xs text-slate-600">
                        <p>{{ $d->description }}</p>
                        @foreach($d->changes ?? [] as $field => $pair)
                            @php [$old, $new] = array_pad((array) $pair, 2, null); @endphp
                            <p><span class="text-slate-500">{{ $field }}:</span>
                                <span class="line-through text-rose-600">{{ is_scalar($old) || $old === null ? ($old ?? '—') : json_encode($old, JSON_UNESCAPED_UNICODE) }}</span> →
                                <span class="text-emerald-700 font-medium">{{ is_scalar($new) || $new === null ? ($new ?? '—') : json_encode($new, JSON_UNESCAPED_UNICODE) }}</span></p>
                        @endforeach
                    </div>
                @endforeach
                @if($l->changes && $g['details']->isEmpty())
                    <div class="mt-1 text-xs">
                        @foreach($l->changes as $field => $pair)
                            @php [$old, $new] = array_pad((array) $pair, 2, null); @endphp
                            <p><span class="text-slate-500">{{ $field }}:</span> <span class="line-through text-rose-600">{{ is_scalar($old) || $old === null ? ($old ?? '—') : json_encode($old, JSON_UNESCAPED_UNICODE) }}</span> → <span class="text-emerald-700 font-medium">{{ is_scalar($new) || $new === null ? ($new ?? '—') : json_encode($new, JSON_UNESCAPED_UNICODE) }}</span></p>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @empty
        <p class="px-4 py-10 text-center text-slate-400">Todavía no hay actividad {{ request()->except('page') ? 'con estos filtros' : '' }}.</p>
    @endforelse
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
