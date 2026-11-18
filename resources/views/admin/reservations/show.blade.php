@extends('admin.layouts.app')
@section('title', 'Apartado '.$reservation->code)
@section('actions')<a href="{{ route('admin.apartados.index') }}" class="text-sm text-slate-600 hover:underline">← Apartados</a>@endsection

@section('content')
@error('reservation')<p class="mb-4 text-sm text-rose-600">{{ $message }}</p>@enderror
<div class="grid lg:grid-cols-3 gap-6 max-w-5xl">
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap justify-between gap-2">
            <div><h2 class="font-bold text-lg">{{ $reservation->code }}</h2><p class="text-xs text-slate-500">{{ $reservation->created_at->isoFormat('D MMM YYYY HH:mm') }} · {{ $reservation->branch->name }} · {{ $reservation->user->name }}</p></div>
            <span class="text-xs font-semibold px-2.5 py-1 h-fit rounded-full {{ ['activo' => $reservation->isExpired() ? 'bg-rose-100 text-rose-700' : 'bg-sky-100 text-sky-800', 'retirado' => 'bg-emerald-100 text-emerald-800', 'cancelado' => 'bg-slate-200 text-slate-600'][$reservation->status] }}">{{ $reservation->isExpired() ? 'Vencido' : ucfirst($reservation->status) }}</span>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-100">
                @foreach($reservation->items as $it)
                    <tr><td class="px-6 py-3"><p class="font-medium">{{ $it->variant->product->name }}</p><p class="text-xs text-slate-500">{{ $it->variant->label() }} · {{ $it->variant->sku }}</p></td><td class="px-3 py-3 text-center">{{ $it->quantity }}</td><td class="px-6 py-3 text-right">{{ money($it->unit_price * $it->quantity) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td colspan="2" class="px-6 py-1 text-right text-slate-500">Total</td><td class="px-6 py-1 text-right">{{ money($reservation->total) }}</td></tr>
                <tr><td colspan="2" class="px-6 py-1 text-right text-slate-500">Adelanto ({{ \App\Services\SaleService::PAYMENTS[$reservation->deposit_method] ?? '' }})</td><td class="px-6 py-1 text-right">− {{ money($reservation->deposit) }}</td></tr>
                <tr><td colspan="2" class="px-6 py-3 text-right font-semibold">Saldo</td><td class="px-6 py-3 text-right text-lg font-bold">{{ money($reservation->balance()) }}</td></tr>
            </tfoot>
        </table>
        @if($reservation->notes)<p class="px-6 pb-4 text-sm text-slate-600 whitespace-pre-line">{{ $reservation->notes }}</p>@endif
    </div>
    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-6 text-sm space-y-2">
            <p class="font-semibold">{{ $reservation->customer->name }}</p>
            <p><a href="{{ $reservation->customer->whatsappLink('¡Hola '.$reservation->customer->firstName().'! Te recordamos que tu apartado '.$reservation->code.' está listo para retirar en RadaWes. Saldo: '.money($reservation->balance()).'.') }}" target="_blank" class="text-wa-500 font-semibold hover:underline">📱 Recordarle por WhatsApp</a></p>
            <p class="text-slate-500">Retira hasta el <strong class="{{ $reservation->isExpired() ? 'text-rose-600' : 'text-slate-800' }}">{{ $reservation->expires_at->isoFormat('dddd D [de] MMMM') }}</strong></p>
            @if($reservation->sale)<p>Venta: <a href="{{ route('admin.ventas.show', $reservation->sale) }}" class="font-semibold text-brand-700 hover:underline">{{ $reservation->sale->code }}</a></p>@endif
        </div>
        @if($reservation->status === 'activo')
            <form method="POST" action="{{ route('admin.apartados.retirar', $reservation) }}" data-once class="bg-white rounded-xl border border-emerald-300 p-6 space-y-3">
                @csrf
                <h3 class="font-semibold text-emerald-800">✅ El cliente retira</h3>
                <p class="text-sm">Cobrar el saldo de <strong>{{ money($reservation->balance()) }}</strong> con:</p>
                <select name="payment_method" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach(\App\Services\SaleService::PAYMENTS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
                <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2.5 rounded-lg">Entregar y registrar la venta</button>
            </form>
            <form method="POST" action="{{ route('admin.apartados.cancelar', $reservation) }}" data-once onsubmit="return confirm('¿Cancelar el apartado {{ $reservation->code }}? Los pares vuelven a estar a la venta.')" class="bg-white rounded-xl border border-rose-200 p-6 space-y-3">
                @csrf
                <h3 class="font-semibold text-rose-700">Cancelar apartado</h3>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="refund" value="1" class="rounded"> Devolver el adelanto de {{ money($reservation->deposit) }}</label>
                <button class="bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Cancelar apartado</button>
            </form>
        @endif
    </div>
</div>
@endsection
