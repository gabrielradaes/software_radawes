@extends('admin.layouts.app')
@section('title', 'Traspaso '.$transfer->code)
@section('actions')<a href="{{ route('admin.traspasos.index') }}" class="text-sm text-slate-600 hover:underline">← Traspasos</a>@endsection

@section('content')
@error('transfer')<p class="mb-4 text-sm text-rose-600">{{ $message }}</p>@enderror
@php $scoped = auth()->user()->scopedBranchId(); @endphp
<div class="grid lg:grid-cols-3 gap-6 max-w-5xl">
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-lg">{{ $transfer->code }} · {{ $transfer->from->name }} → {{ $transfer->to->name }}</h2>
            <p class="text-xs text-slate-500">Enviado {{ $transfer->created_at->isoFormat('D MMM YYYY HH:mm') }} por {{ $transfer->sender->name }}@if($transfer->received_at) · recibido {{ $transfer->received_at->isoFormat('D MMM HH:mm') }} por {{ $transfer->receiver?->name }}@endif</p>
            @if($transfer->notes)<p class="text-sm text-slate-600 mt-1">{{ $transfer->notes }}</p>@endif
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-100">
                @foreach($transfer->items as $it)
                    <tr><td class="px-6 py-3"><p class="font-medium">{{ $it->variant->product->name }} @if($it->variant->product->code)<span class="text-xs text-slate-400">({{ $it->variant->product->code }})</span>@endif</p><p class="text-xs text-slate-500">{{ $it->variant->label() }} · {{ $it->variant->sku }}</p></td><td class="px-6 py-3 text-right font-semibold">{{ $it->quantity }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td class="px-6 py-3 text-right font-semibold">Total</td><td class="px-6 py-3 text-right font-bold">{{ $transfer->pairs }} pares</td></tr></tfoot>
        </table>
    </div>
    <div class="space-y-4">
        @if($transfer->status === 'enviado')
            <p class="text-sm bg-amber-50 border border-amber-200 text-amber-900 rounded-xl px-4 py-3">🚚 <strong>En camino.</strong> Los pares ya salieron de {{ $transfer->from->name }} y entrarán al stock de {{ $transfer->to->name }} al confirmar.</p>
            @if(! $scoped || $scoped === $transfer->to_branch_id)
                <form method="POST" action="{{ route('admin.traspasos.recibir', $transfer) }}" data-once onsubmit="return confirm('¿Llegaron los {{ $transfer->pairs }} pares completos?')">
                    @csrf<button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-3 rounded-lg">✔ Confirmar recepción</button>
                </form>
            @endif
            @if((! $scoped || $scoped === $transfer->from_branch_id) && auth()->user()->can('stock.transfer'))
                <form method="POST" action="{{ route('admin.traspasos.cancelar', $transfer) }}" data-once onsubmit="return confirm('¿Cancelar? Los pares vuelven a {{ $transfer->from->name }}.')">
                    @csrf<button class="w-full bg-white border border-rose-300 text-rose-700 hover:bg-rose-50 font-semibold px-4 py-2 rounded-lg">Cancelar envío</button>
                </form>
            @endif
        @else
            <p class="text-sm rounded-xl px-4 py-3 {{ $transfer->status === 'recibido' ? 'bg-emerald-50 border border-emerald-200 text-emerald-900' : 'bg-slate-100 text-slate-600' }}">{{ $transfer->status === 'recibido' ? '✔ Recibido completo.' : 'Cancelado: los pares volvieron al origen.' }}</p>
        @endif
    </div>
</div>
@endsection
