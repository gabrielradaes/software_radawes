@extends('admin.layouts.app')
@section('title', 'Sucursales')
@section('actions')<a href="{{ route('admin.sucursales.create') }}" class="bg-brand-900 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ Nueva sucursal</a>@endsection

@section('content')
<div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
    @foreach($branches as $b)
        <div class="bg-white rounded-xl border border-slate-200 p-5 {{ $b->is_active ? '' : 'opacity-60' }}">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="font-bold text-lg">{{ $b->name }}</h2>
                    <p class="text-sm text-slate-500">{{ $b->city }}, {{ $b->country }} · <a href="{{ route('home', $b) }}" target="_blank" class="hover:underline">/sucursal/{{ $b->slug }} ↗</a></p>
                </div>
                @if($b->is_main)<span class="text-[11px] font-bold bg-gold-400/40 px-2 py-0.5 rounded-full">Principal</span>@endif
            </div>
            <p class="text-sm mt-3">{{ $b->address }}</p>
            <p class="text-sm text-wa-500 font-semibold">+{{ $b->whatsapp }}</p>
            <div class="mt-4 grid grid-cols-3 text-center text-xs text-slate-500 border-t border-slate-100 pt-3">
                <div><p class="text-lg font-bold text-slate-800">{{ $b->users_count }}</p>usuarios</div>
                <div><p class="text-lg font-bold text-slate-800">{{ $b->stocks_count }}</p>variantes</div>
                <div><p class="text-lg font-bold text-slate-800">{{ $b->whatsapp_leads_count }}</p>leads</div>
            </div>
            <div class="mt-4 flex gap-3 text-xs">
                <a href="{{ route('admin.sucursales.edit', $b) }}" class="font-semibold text-brand-500 hover:underline">Editar</a>
                <form method="POST" action="{{ route('admin.sucursales.destroy', $b) }}" onsubmit="return confirmar('¿Eliminar/desactivar {{ $b->name }}?')">@csrf @method('DELETE')<button class="text-rose-600 hover:underline">Eliminar</button></form>
            </div>
        </div>
    @endforeach
</div>
@endsection
