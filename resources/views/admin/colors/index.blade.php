@extends('admin.layouts.app')
@section('title', 'Colores de la tienda')
@section('actions')
    <a href="{{ route('home', $branch) }}" target="_blank" class="text-sm font-semibold text-brand-500 hover:underline">Ver {{ $branch->brandName() }} ↗</a>
@endsection

@section('content')
@if($branches->count() > 1)
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach($branches as $b)
            <a href="{{ route('admin.colores.index', ['tienda' => $b->id]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-full border text-sm font-medium {{ $b->id === $branch->id ? 'bg-slate-900 text-white border-slate-900' : 'bg-white border-slate-200 hover:bg-slate-50' }}">
                <span class="w-3 h-3 rounded-full ring-1 ring-black/10" style="background: {{ $b->color_primary }}"></span>{{ $b->brandName() }} · {{ $b->city }}
            </a>
        @endforeach
    </div>
@endif

<div class="bg-white rounded-xl border border-slate-200 p-5 mb-6 flex flex-wrap items-center gap-6">
    <div class="flex items-center gap-3">
        @if($branch->logoUrl())<img src="{{ $branch->logoUrl() }}" class="h-12 w-auto object-contain">@endif
        <div>
            <p class="font-bold text-lg">{{ $branch->name }}</p>
            <p class="text-sm text-slate-500">Paleta actual: <strong>{{ $current ? $current.' · '.$palettes[$current]['name'] : 'personalizada' }}</strong></p>
        </div>
    </div>
    <div class="flex items-center gap-2 ml-auto">
        <span class="w-9 h-9 rounded-full ring-1 ring-black/10" style="background: {{ $branch->color_primary }}" title="Primario"></span>
        <span class="w-9 h-9 rounded-full ring-1 ring-black/10" style="background: {{ $branch->color_accent }}" title="Acento"></span>
        <span class="w-9 h-9 rounded-full ring-1 ring-black/10" style="background: {{ $branch->color_surface }}" title="Fondo"></span>
    </div>
</div>

<p class="text-sm text-slate-600 mb-4">Elige una combinación. <strong>Vista previa</strong> abre la tienda con esos colores solo para ti; <strong>Usar</strong> la aplica para todos los clientes. Puedes cambiarla cuantas veces quieras.</p>

<div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    @foreach($palettes as $n => $p)
        @php $dark = \App\Support\Theme::shade($p['primary'], -0.55); $light = \App\Support\Theme::shade($p['primary'], 0.9); @endphp
        <div class="bg-white rounded-xl border {{ $current === $n ? 'border-emerald-500 ring-2 ring-emerald-200' : 'border-slate-200' }} overflow-hidden flex flex-col">
            {{-- Mini vista de la tienda con esos colores --}}
            <div class="p-3" style="background: {{ $p['surface'] }}">
                <div class="rounded-lg overflow-hidden border border-black/5 bg-white text-[9px]">
                    <div class="px-2 py-1 flex items-center justify-between" style="background: {{ $light }}; color: {{ $dark }}">
                        <span>📍 Tienda · Lun–Sáb</span><span class="font-semibold" style="color: #1ebe5b">WhatsApp</span>
                    </div>
                    <div class="px-2 py-1.5 flex items-center gap-1.5 border-b border-black/5">
                        <span class="w-4 h-4 rounded-full flex items-center justify-center text-[7px] font-bold text-white" style="background: {{ $dark }}">RW</span>
                        <span class="font-bold" style="color: {{ $dark }}">{{ $branch->brandName() }}</span>
                        <span class="ml-auto flex gap-1.5" style="color: {{ $dark }}"><span>Inicio</span><span>Calzado</span><span>Novedades</span></span>
                    </div>
                    <div class="px-2 py-2.5 grid grid-cols-[1fr_auto] gap-2 items-center">
                        <div>
                            <p class="uppercase tracking-wider text-[7px] font-semibold" style="color: {{ \App\Support\Theme::shade($p['accent'], -0.2) }}">Hecho en Brasil</p>
                            <p class="font-bold text-[11px] leading-tight" style="color: {{ $dark }}">Calzado elegante<br>para hombre</p>
                            <div class="flex gap-1 mt-1.5">
                                <span class="px-2 py-0.5 rounded-full text-white font-semibold" style="background: {{ $dark }}">Ver catálogo</span>
                                <span class="px-2 py-0.5 rounded-full text-white font-semibold" style="background: #25D366">Pedir</span>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="w-9 h-7 rounded" style="background: {{ $p['primary'] }}"></span>
                            <span class="px-1 rounded text-white text-[7px] font-bold text-center" style="background: {{ $p['accent'] }}">-15%</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-3 flex-1 flex flex-col">
                <div class="flex items-center justify-between">
                    <p class="font-bold">{{ $n }} <span class="text-slate-400 font-normal text-xs">/ {{ count($palettes) }}</span></p>
                    @if($current === $n)<span class="text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full">En uso</span>@endif
                </div>
                <p class="text-sm text-slate-700">{{ $p['name'] }}</p>
                <div class="flex gap-1.5 mt-2">
                    <span class="w-5 h-5 rounded-full ring-1 ring-black/10" style="background: {{ $p['primary'] }}"></span>
                    <span class="w-5 h-5 rounded-full ring-1 ring-black/10" style="background: {{ $p['accent'] }}"></span>
                    <span class="w-5 h-5 rounded-full ring-1 ring-black/10" style="background: {{ $p['surface'] }}"></span>
                </div>
                <div class="mt-3 flex gap-2">
                    <a href="{{ route('home', $branch) }}?paleta={{ $n }}" target="_blank" class="flex-1 text-center text-xs font-semibold border border-slate-300 hover:bg-slate-50 rounded-lg py-1.5">Vista previa</a>
                    @if($current !== $n)
                        <form method="POST" action="{{ route('admin.colores.store') }}" class="flex-1">
                            @csrf
                            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                            <input type="hidden" name="paleta" value="{{ $n }}">
                            <button class="w-full text-xs font-semibold text-white rounded-lg py-1.5" style="background: {{ $dark }}">Usar</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
