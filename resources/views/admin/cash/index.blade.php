@extends('admin.layouts.app')
@section('title', 'Caja · '.$branch->name)

@section('content')
@unless($scoped)
    <form method="GET" class="mb-4 flex items-center gap-2 text-sm">
        <span class="text-slate-500">Tienda:</span>
        <select name="sucursal" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach($branches as $b)<option value="{{ $b->id }}" @selected($b->id === $branch->id)>{{ $b->name }}</option>@endforeach
        </select>
    </form>
@endunless
@error('caja')<p class="mb-4 text-sm text-rose-600">{{ $message }}</p>@enderror

<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        @if(! $open)
            <form method="POST" action="{{ route('admin.caja.abrir') }}" data-once class="bg-white rounded-xl border border-slate-200 p-6 space-y-4 max-w-md">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <h3 class="font-semibold text-lg">🔓 Abrir caja</h3>
                <p class="text-sm text-slate-500">Cuenta el efectivo con el que empiezas el día (cambio) y ábrela. Las ventas de la tienda se irán sumando a este turno.</p>
                <x-admin.field label="Efectivo inicial (Bs.)" name="opening_amount">
                    <input type="number" step="0.01" min="0" name="opening_amount" value="{{ old('opening_amount', 0) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-lg">
                </x-admin.field>
                <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-lg">Abrir caja</button>
            </form>
        @else
            <div class="bg-white rounded-xl border border-emerald-300 p-6">
                <div class="flex flex-wrap justify-between gap-2 mb-4">
                    <h3 class="font-semibold text-lg">🟢 Caja abierta</h3>
                    <p class="text-sm text-slate-500">desde {{ $open->opened_at->isoFormat('ddd D MMM, HH:mm') }} · por {{ $open->opener->name }}</p>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                    <div class="bg-slate-50 rounded-lg p-3"><p class="text-xs text-slate-500">Inicial</p><p class="font-bold">{{ money($open->opening_amount) }}</p></div>
                    @foreach(\App\Services\SaleService::PAYMENTS as $k => $label)
                        <div class="bg-slate-50 rounded-lg p-3"><p class="text-xs text-slate-500">{{ $label }} <span class="text-slate-400">({{ $totals[$k]['count'] ?? 0 }})</span></p><p class="font-bold">{{ money($totals[$k]['total'] ?? 0) }}</p></div>
                    @endforeach
                </div>
                <p class="mt-4 text-sm">Movimiento neto del turno: <strong>{{ money(collect($totals)->sum('total')) }}</strong> · Efectivo que debería haber en caja: <strong class="text-lg">{{ money($expected) }}</strong></p>
            </div>

            <div class="bg-white rounded-xl border border-slate-200">
                <div class="px-5 py-3 border-b border-slate-100 flex flex-wrap justify-between gap-2 items-center">
                    <span class="font-semibold">Movimientos del turno</span>
                    <form method="POST" action="{{ route('admin.caja.movimiento', $open) }}" data-once class="flex flex-wrap gap-2 items-center text-sm">
                        @csrf
                        <select name="type" class="rounded-lg border border-slate-300 px-2 py-1.5"><option value="egreso">− Gasto / retiro</option><option value="ingreso">+ Ingreso</option></select>
                        <input type="number" step="0.01" min="0.01" name="amount" required placeholder="Bs" class="w-24 rounded-lg border border-slate-300 px-2 py-1.5">
                        <select name="payment_method" class="rounded-lg border border-slate-300 px-2 py-1.5">@foreach(\App\Services\SaleService::PAYMENTS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                        <input name="note" required maxlength="200" placeholder="Motivo (ej.: pasaje)" class="w-40 rounded-lg border border-slate-300 px-2 py-1.5">
                        <button class="bg-slate-800 text-white font-semibold px-3 py-1.5 rounded-lg">Registrar</button>
                    </form>
                </div>
                <ul class="divide-y divide-slate-100 max-h-80 overflow-y-auto">
                    @forelse($open->movements()->with('user')->get() as $m)
                        <li class="px-5 py-2 text-sm flex justify-between gap-3">
                            <span>{{ \App\Models\CashMovement::TYPES[$m->type] ?? $m->type }} <span class="text-xs text-slate-400">· {{ $m->created_at->format('H:i') }} · {{ \App\Services\SaleService::PAYMENTS[$m->payment_method] ?? $m->payment_method }} · {{ $m->note }}</span></span>
                            <span class="font-semibold whitespace-nowrap {{ $m->amount < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $m->amount < 0 ? '−' : '+' }} {{ money(abs($m->amount)) }}</span>
                        </li>
                    @empty<li class="px-5 py-4 text-sm text-slate-400">Sin movimientos todavía.</li>@endforelse
                </ul>
            </div>

            <form method="POST" action="{{ route('admin.caja.cerrar', $open) }}" data-once x-data="{ counted: '' }" class="bg-white rounded-xl border border-slate-200 p-6 space-y-4 max-w-md">
                @csrf
                <h3 class="font-semibold text-lg">🔒 Cerrar caja (arqueo)</h3>
                <x-admin.field label="Efectivo contado (Bs.)" name="counted_cash">
                    <input type="number" step="0.01" min="0" name="counted_cash" x-model="counted" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-lg">
                </x-admin.field>
                <p x-show="counted !== ''" class="text-sm" :class="Math.abs(counted - {{ $expected }}) < 0.01 ? 'text-emerald-700' : 'text-rose-600'"
                   x-text="Math.abs(counted - {{ $expected }}) < 0.01 ? '✔ Cuadra perfecto' : ((counted - {{ $expected }}) > 0 ? 'Sobran Bs ' : 'Faltan Bs ') + Math.abs(counted - {{ $expected }}).toFixed(2)"></p>
                <x-admin.field label="Notas" name="notes"><input name="notes" maxlength="500" placeholder="Ej.: se pagó el pasaje del mensajero" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
                <button class="bg-slate-800 hover:bg-slate-700 text-white font-semibold px-5 py-2.5 rounded-lg">Cerrar caja</button>
            </form>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-200 h-fit">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold">Cierres anteriores</div>
        <ul class="divide-y divide-slate-100">
            @forelse($history as $h)
                <li class="px-5 py-3 text-sm">
                    <p class="font-medium">{{ $h->closed_at->isoFormat('ddd D MMM') }} <span class="text-xs text-slate-400 font-normal">{{ $h->opened_at->format('H:i') }}–{{ $h->closed_at->format('H:i') }} · {{ $h->closer?->name }}</span></p>
                    <p class="text-xs text-slate-500">Vendido {{ money(collect($h->totals)->sum('total')) }} · efectivo esperado {{ money($h->expected_cash) }} · contado {{ money($h->counted_cash) }}</p>
                    <p class="text-xs font-semibold {{ abs($h->difference) < 0.01 ? 'text-emerald-700' : 'text-rose-600' }}">{{ abs($h->difference) < 0.01 ? '✔ Cuadró' : 'Diferencia '.money($h->difference) }}</p>
                    @if($h->notes)<p class="text-xs text-slate-500 italic">{{ $h->notes }}</p>@endif
                </li>
            @empty<li class="px-5 py-5 text-sm text-slate-400">Sin cierres todavía.</li>@endforelse
        </ul>
    </div>
</div>
@endsection
