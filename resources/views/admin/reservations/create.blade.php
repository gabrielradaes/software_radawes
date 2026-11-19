@extends('admin.layouts.app')
@section('title', 'Nuevo apartado')
@section('actions')<a href="{{ route('admin.apartados.index') }}" class="text-sm text-slate-600 hover:underline">← Apartados</a>@endsection

@section('content')
@unless($scoped)
    <form method="GET" class="mb-4 flex items-center gap-2 text-sm">
        <span class="text-slate-500">Tienda:</span>
        <select name="sucursal" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach($branches as $b)<option value="{{ $b->id }}" @selected($b->id === $branch->id)>{{ $b->name }}</option>@endforeach
        </select>
    </form>
@endunless
<form method="POST" action="{{ route('admin.apartados.store') }}" data-once class="grid lg:grid-cols-3 gap-6">
    @csrf
    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
    <div class="lg:col-span-2 min-w-0 bg-white rounded-xl border border-slate-200 p-6 space-y-3">
        <h3 class="font-semibold">Pares que aparta · {{ $branch->name }}</h3>
        @error('items')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
        @include('admin.partials.items-picker', ['name' => 'items', 'branchId' => $branch->id, 'withPrice' => true, 'old' => old('items')])
    </div>
    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-3">
            <h3 class="font-semibold">Cliente</h3>
            <x-admin.field label="Celular" name="customer_phone"><input name="customer_phone" required value="{{ old('customer_phone', $customer?->phone) }}" inputmode="tel" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
            <x-admin.field label="Nombre (si es nuevo)" name="customer_name"><input name="customer_name" value="{{ old('customer_name', $customer?->name) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <x-admin.field label="Adelanto (Bs.)" name="deposit"><input type="number" step="0.01" min="0" name="deposit" required value="{{ old('deposit') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
                <x-admin.field label="Pagó con" name="deposit_method">
                    <select name="deposit_method" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach(\App\Services\SaleService::PAYMENTS as $k => $l)<option value="{{ $k }}" @selected(old('deposit_method') === $k)>{{ $l }}</option>@endforeach
                    </select>
                </x-admin.field>
            </div>
            <x-admin.field label="Retira hasta" name="expires_at"><input type="date" name="expires_at" required min="{{ today()->toDateString() }}" value="{{ old('expires_at', today()->addDays(7)->toDateString()) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
            <x-admin.field label="Notas" name="notes"><input name="notes" value="{{ old('notes') }}" maxlength="500" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
            <p class="text-xs text-slate-500">Los pares quedan <strong>reservados</strong>: no se pueden vender a otro cliente. Un día antes de la fecha límite aparece un seguimiento en el CRM para recordarle.</p>
            <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-3 rounded-lg">Registrar apartado</button>
        </div>
    </div>
</form>
@endsection
