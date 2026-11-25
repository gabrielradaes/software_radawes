@extends('admin.layouts.app')
@section('title', 'Chats del equipo con el asistente')
@section('actions')
    <a href="{{ route('admin.asistente') }}" class="text-sm text-slate-600 hover:underline">← Mi asistente</a>
@endsection

@section('content')
@php
    $labels = ['nuevo_producto' => 'Producto nuevo', 'entrada' => 'Entrada de stock', 'salida' => 'Salida de stock', 'consulta' => 'Consulta de stock', 'venta' => 'Venta', 'publicar' => 'Publicación', 'traspaso' => 'Traspaso'];
@endphp
<div class="grid lg:grid-cols-[260px_1fr] gap-5">
    {{-- Personas --}}
    <aside class="bg-white rounded-2xl border border-slate-200 overflow-hidden self-start">
        <p class="px-4 py-3 text-xs uppercase tracking-wide text-slate-500 border-b border-slate-100">Quién usó el asistente</p>
        @forelse($people as $u)
            <a href="{{ route('admin.asistente.equipo', ['usuario' => $u->id]) }}" class="flex items-center gap-3 px-4 py-3 border-b border-slate-100 last:border-0 {{ $person?->id === $u->id ? 'bg-brand-50' : 'hover:bg-slate-50' }}">
                <span class="w-9 h-9 rounded-full bg-brand-900 text-white text-xs font-bold flex items-center justify-center shrink-0">{{ $u->initials() }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-sm truncate">{{ $u->name }}</span>
                    <span class="block text-xs text-slate-500 truncate">{{ $u->role?->label }}{{ $u->branch ? ' · '.\App\Assistant\Assistant::short($u->branch) : '' }}</span>
                    <span class="block text-[11px] text-slate-400">{{ $u->asked }} mensaje(s) · {{ $u->last_at ? \Illuminate\Support\Carbon::parse($u->last_at)->diffForHumans() : '' }}</span>
                </span>
            </a>
        @empty
            <p class="px-4 py-6 text-sm text-slate-500">Nadie usó el asistente todavía.</p>
        @endforelse
    </aside>

    {{-- Conversación --}}
    <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        @if($person)
            <div class="px-5 py-3 border-b border-slate-100 flex flex-wrap items-center gap-2">
                <h2 class="font-semibold">{{ $person->name }}</h2>
                <span class="text-xs text-slate-500">últimos {{ $messages->count() }} mensajes · solo lectura</span>
            </div>
            <div class="p-4 space-y-3 bg-slate-50 max-h-[75vh] overflow-y-auto" x-data x-init="$el.scrollTop = $el.scrollHeight">
                @php $lastDate = null; @endphp
                @foreach($messages as $m)
                    @if($m['date'] !== $lastDate)
                        <p class="text-center text-[11px] font-semibold uppercase tracking-wide text-slate-400 pt-2">{{ $m['date'] }}</p>
                        @php $lastDate = $m['date']; @endphp
                    @endif
                    <div class="flex {{ $m['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[88%] px-4 py-2.5 text-sm leading-relaxed shadow-sm rounded-2xl {{ $m['role'] === 'user' ? 'bg-brand-700 text-white rounded-br-md' : 'bg-white border border-slate-200 text-slate-800 rounded-bl-md' }} {{ $m['hidden'] ? 'opacity-60' : '' }}">
                            <div class="text-[11px] mb-0.5 {{ $m['role'] === 'user' ? 'text-white/70' : 'text-brand-700 font-semibold' }}">
                                {{ $m['role'] === 'user' ? $person->firstName() : '🤖 Asistente' }} · {{ $m['time'] }}
                                @if($m['role'] !== 'user' && ($labels[$m['intent']] ?? null)) · {{ $labels[$m['intent']] }} @endif
                                @if($m['hidden']) · <span title="La persona tocó «Nueva conversación»">oculto en su pantalla</span> @endif
                            </div>
                            <div>{!! $m['html'] !!}</div>
                            @if($m['table'])
                                <div class="mt-2 overflow-x-auto">
                                    <table class="text-xs w-full">
                                        <thead><tr>@foreach($m['table'][0] as $h)<th class="text-left text-slate-500 border-b border-slate-200 px-2 py-1">{{ $h }}</th>@endforeach</tr></thead>
                                        <tbody>@foreach($m['table'][1] as $row)<tr class="border-b border-slate-100 last:border-0">@foreach($row as $c)<td class="px-2 py-1">{{ $c }}</td>@endforeach</tr>@endforeach</tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="px-5 py-10 text-center text-sm text-slate-500">Cuando alguien use el asistente, aquí verás lo que le pidió y lo que respondió.</p>
        @endif
    </section>
</div>
@endsection
