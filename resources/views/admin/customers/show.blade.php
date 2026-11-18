@extends('admin.layouts.app')
@section('title', 'Cliente: '.$customer->name)
@section('actions')
    <a href="{{ route('admin.clientes.index') }}" class="text-sm text-slate-600 hover:underline">← Clientes</a>
    @can('sales.create')<a href="{{ route('admin.ventas.create', ['cliente' => $customer->id]) }}" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">🧾 Nueva venta</a>@endcan
@endsection

@section('content')
<div class="grid lg:grid-cols-3 gap-6">
    <div class="space-y-6">
        {{-- Datos --}}
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <div class="flex items-center gap-4">
                <span class="w-14 h-14 rounded-full bg-brand-100 text-brand-700 font-bold text-xl flex items-center justify-center">{{ $customer->initials() }}</span>
                <div>
                    <h2 class="font-bold text-lg leading-tight">{{ $customer->name }}</h2>
                    <p class="mt-1 flex flex-wrap gap-1">
                        <span class="text-[11px] px-2 py-0.5 rounded-full {{ $customer->stageClass() }}">{{ $customer->stageLabel() }}</span>
                        @foreach($customer->tags as $t)<span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-800 text-white">{{ $t->name }}</span>@endforeach
                    </p>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2 mt-5 text-center">
                <div class="bg-slate-50 rounded-lg p-2"><p class="text-[11px] text-slate-500">Compras</p><p class="font-bold">{{ $stats['count'] }}</p></div>
                <div class="bg-slate-50 rounded-lg p-2"><p class="text-[11px] text-slate-500">Total</p><p class="font-bold text-sm">{{ money($stats['spent']) }}</p></div>
                <div class="bg-slate-50 rounded-lg p-2"><p class="text-[11px] text-slate-500">Última</p><p class="font-bold text-sm">{{ $stats['last'] ? \Illuminate\Support\Carbon::parse($stats['last'])->diffForHumans(null, true) : '—' }}</p></div>
            </div>
            <dl class="mt-5 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Celular</dt><dd>@if($customer->phone)<a href="{{ $customer->whatsappLink('¡Hola '.$customer->firstName().'! Te escribimos de RadaWes.') }}" target="_blank" class="text-wa-500 font-semibold hover:underline">📱 {{ $customer->phone }}</a>@else — @endif</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Correo</dt><dd class="truncate">{{ $customer->email ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Ciudad</dt><dd>{{ $customer->city ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">CI / NIT</dt><dd>{{ $customer->document ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Talla habitual</dt><dd>{{ $customer->preferred_size ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Nos conoció por</dt><dd>{{ \App\Models\Customer::SOURCES[$customer->source] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Tienda</dt><dd>{{ $customer->branch?->name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Cuenta web</dt><dd>{{ $customer->password ? ($customer->email_verified_at ? '✓ verificada' : 'sin verificar') : 'no tiene' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Desde</dt><dd>{{ $customer->created_at->isoFormat('D MMM YYYY') }}</dd></div>
            </dl>
        </div>

        {{-- CRM --}}
        <form method="POST" action="{{ route('admin.clientes.crm', $customer) }}" class="bg-white rounded-xl border border-slate-200 p-6 space-y-3">
            @csrf @method('PATCH')
            <h3 class="font-semibold">CRM</h3>
            <label class="block text-sm"><span class="text-slate-500 text-xs">Etapa</span>
                <select name="stage" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach(\App\Models\Customer::STAGES as $k => [$label])<option value="{{ $k }}" @selected($customer->stage === $k)>{{ $label }}</option>@endforeach
                </select></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm"><span class="text-slate-500 text-xs">Talla habitual</span>
                    <input name="preferred_size" value="{{ $customer->preferred_size }}" maxlength="10" placeholder="41" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="block text-sm"><span class="text-slate-500 text-xs">Nos conoció por</span>
                    <select name="source" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">—</option>
                        @foreach(\App\Models\Customer::SOURCES as $k => $label)<option value="{{ $k }}" @selected($customer->source === $k)>{{ $label }}</option>@endforeach
                    </select></label>
            </div>
            <label class="block text-sm"><span class="text-slate-500 text-xs">Etiquetas (separadas por coma)</span>
                <input name="tags" value="{{ $customer->tags->pluck('name')->implode(', ') }}" placeholder="VIP, Mayorista" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <button class="bg-brand-900 text-white text-sm font-semibold px-4 py-2 rounded-lg">Guardar</button>
        </form>

        @can('customers.manage')
            <form method="POST" action="{{ route('admin.clientes.update', $customer) }}" class="bg-white rounded-xl border border-slate-200 p-6 space-y-3">
                @csrf @method('PATCH')
                <h3 class="font-semibold">Notas fijas</h3>
                <textarea name="notes" rows="3" placeholder="Preferencias, observaciones…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $customer->notes) }}</textarea>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($customer->is_active) class="rounded"> Cuenta activa (puede ingresar y comentar)</label>
                <button class="bg-slate-800 text-white text-sm font-semibold px-4 py-2 rounded-lg">Guardar</button>
            </form>
        @elseif($customer->notes)
            <div class="bg-white rounded-xl border border-slate-200 p-6"><h3 class="font-semibold mb-2">Notas fijas</h3><p class="text-sm whitespace-pre-line">{{ $customer->notes }}</p></div>
        @endcan
    </div>

    <div class="lg:col-span-2 space-y-6">
        {{-- Seguimientos --}}
        <div class="bg-white rounded-xl border border-slate-200">
            <div class="px-5 py-4 border-b border-slate-100 font-semibold">📅 Seguimientos</div>
            <form method="POST" action="{{ route('admin.crm.tareas.store') }}" class="px-5 py-4 flex flex-wrap gap-2 border-b border-slate-100">
                @csrf
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                <input name="title" required maxlength="200" placeholder="Ej.: Avisarle cuando llegue la talla 41" class="flex-1 min-w-[200px] rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="datetime-local" name="due_at" required value="{{ now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="bg-brand-900 text-white text-sm font-semibold px-4 py-2 rounded-lg">Agendar</button>
            </form>
            <ul class="divide-y divide-slate-100">
                @forelse($customer->tasks as $t)
                    <li class="px-5 py-3 text-sm flex items-center gap-3 {{ $t->done_at ? 'opacity-50' : '' }}">
                        <form method="POST" action="{{ route('admin.crm.tareas.toggle', $t) }}">@csrf @method('PATCH')<button class="w-6 h-6 rounded border {{ $t->done_at ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-slate-300' }} text-xs">{{ $t->done_at ? '✓' : '' }}</button></form>
                        <div class="flex-1"><p class="{{ $t->done_at ? 'line-through' : '' }}">{{ $t->title }}</p><p class="text-xs {{ $t->isOverdue() ? 'text-rose-600 font-semibold' : 'text-slate-400' }}">{{ $t->due_at->isoFormat('ddd D MMM, HH:mm') }} · {{ $t->user->name }}</p></div>
                    </li>
                @empty<li class="px-5 py-5 text-sm text-slate-400">Sin seguimientos.</li>@endforelse
            </ul>
        </div>

        {{-- Anotar --}}
        <form method="POST" action="{{ route('admin.clientes.historial', $customer) }}" class="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
            @csrf
            <div class="flex flex-wrap gap-2">
                @foreach(\App\Models\CustomerInteraction::TYPES as $k => $label)
                    <label class="text-sm"><input type="radio" name="type" value="{{ $k }}" @checked($k === 'nota') class="peer sr-only"><span class="inline-block px-3 py-1.5 rounded-full border border-slate-300 cursor-pointer peer-checked:bg-brand-900 peer-checked:text-white peer-checked:border-brand-900">{{ $label }}</span></label>
                @endforeach
            </div>
            <textarea name="body" required rows="2" maxlength="2000" placeholder="¿Qué pasó? Ej.: Preguntó por mocasines café talla 41, volverá el sábado." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            <button class="bg-brand-900 text-white text-sm font-semibold px-4 py-2 rounded-lg">Anotar en el historial</button>
        </form>

        {{-- Historial --}}
        <div class="bg-white rounded-xl border border-slate-200">
            <div class="px-5 py-4 border-b border-slate-100 font-semibold">🕒 Historial ({{ $timeline->count() }})</div>
            <ul class="divide-y divide-slate-100">
                @forelse($timeline as $e)
                    <li class="px-5 py-3 text-sm flex gap-3">
                        <span class="text-lg leading-none mt-0.5">{{ $e['icon'] }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium">@isset($e['url'])<a href="{{ $e['url'] }}" class="hover:underline">{{ $e['title'] }}</a>@else{{ $e['title'] }}@endisset <span class="text-xs text-slate-400 font-normal">· {{ $e['at']->isoFormat('D MMM YYYY HH:mm') }}</span></p>
                            @if($e['body'])<p class="text-slate-600 whitespace-pre-line">{{ $e['body'] }}</p>@endif
                        </div>
                    </li>
                @empty<li class="px-5 py-6 text-sm text-slate-400">Todavía no hay actividad.</li>@endforelse
            </ul>
        </div>

        <div class="bg-white rounded-xl border border-slate-200">
            <div class="px-5 py-4 border-b border-slate-100 font-semibold">❤️ Favoritos en la página ({{ $customer->likes->count() }})</div>
            <ul class="divide-y divide-slate-100">
                @forelse($customer->likes as $l)
                    <li class="px-5 py-2.5 text-sm">{{ $l->likeable instanceof \App\Models\Product ? '👞' : '📣' }} {{ $l->likeable?->name ?? $l->likeable?->title ?? '—' }}</li>
                @empty<li class="px-5 py-5 text-sm text-slate-400">Sin favoritos.</li>@endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
