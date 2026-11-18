@extends('admin.layouts.app')
@section('title', 'Inventario físico')

@section('content')
@error('count')<p class="mb-4 text-sm text-rose-600">{{ $message }}</p>@enderror
<div class="grid lg:grid-cols-3 gap-6">
    @can('stock.adjust')
        <form method="POST" action="{{ route('admin.inventario.store') }}" data-once class="bg-white rounded-xl border border-slate-200 p-6 space-y-4 h-fit">
            @csrf
            <h3 class="font-semibold">Empezar un conteo</h3>
            <x-admin.field label="Tienda" name="branch_id">
                <select name="branch_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </x-admin.field>
            <div class="space-y-2 text-sm">
                <label class="flex gap-2"><input type="radio" name="scope" value="parcial" checked class="mt-1"><span><strong>Parcial</strong> — solo ajusta lo que cuentes (ej.: una estantería o un modelo).</span></label>
                <label class="flex gap-2"><input type="radio" name="scope" value="total" class="mt-1"><span><strong>Total</strong> — toda la tienda: lo que <em>no</em> cuentes queda en 0.</span></label>
            </div>
            <x-admin.field label="Notas" name="notes"><input name="notes" maxlength="255" placeholder="Ej.: inventario de fin de mes" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
            <button class="bg-brand-900 text-white text-sm font-semibold px-4 py-2 rounded-lg">Empezar</button>
        </form>
    @endcan
    <div class="{{ auth()->user()->can('stock.adjust') ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white rounded-xl border border-slate-200 divide-y divide-slate-100 h-fit">
        @forelse($counts as $c)
            <a href="{{ route('admin.inventario.show', $c) }}" class="flex flex-wrap justify-between gap-2 p-4 hover:bg-slate-50">
                <div><p class="font-semibold">Inventario #{{ $c->id }} · {{ $c->branch->name }} <span class="text-xs font-normal text-slate-500">({{ $c->scope }})</span></p>
                    <p class="text-xs text-slate-500">{{ $c->created_at->isoFormat('D MMM YYYY HH:mm') }} · {{ $c->user->name }} @if($c->notes)· {{ $c->notes }}@endif</p></div>
                <div class="text-right"><span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ ['abierto' => 'bg-amber-100 text-amber-800', 'cerrado' => 'bg-emerald-100 text-emerald-800', 'cancelado' => 'bg-slate-200 text-slate-600'][$c->status] }}">{{ ucfirst($c->status) }}</span>
                    <p class="text-xs text-slate-500 mt-1">{{ (int) $c->items_sum_counted }} pares contados</p></div>
            </a>
        @empty
            <p class="p-6 text-sm text-slate-400">Aún no hay inventarios. Un conteo se hace escaneando las etiquetas con el celular o un lector.</p>
        @endforelse
    </div>
</div>
<div class="mt-4">{{ $counts->links() }}</div>
@endsection
