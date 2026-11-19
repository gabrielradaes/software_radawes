@extends('admin.layouts.app')
@section('title', 'Enviar a otra tienda')
@section('actions')<a href="{{ route('admin.traspasos.index') }}" class="text-sm text-slate-600 hover:underline">← Traspasos</a>@endsection

@section('content')
@unless($scoped)
    <form method="GET" class="mb-4 flex items-center gap-2 text-sm">
        <span class="text-slate-500">Envía:</span>
        <select name="desde" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach($origins as $b)<option value="{{ $b->id }}" @selected($b->id === $from->id)>{{ $b->name }}</option>@endforeach
        </select>
    </form>
@endunless
<form method="POST" action="{{ route('admin.traspasos.store') }}" data-once class="grid lg:grid-cols-3 gap-6">
    @csrf
    <input type="hidden" name="from_branch_id" value="{{ $from->id }}">
    <div class="lg:col-span-2 min-w-0 bg-white rounded-xl border border-slate-200 p-6 space-y-3">
        <h3 class="font-semibold">Pares que salen de {{ $from->name }}</h3>
        @error('items')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
        @include('admin.partials.items-picker', ['name' => 'items', 'branchId' => $from->id, 'withPrice' => false, 'old' => old('items')])
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-3 h-fit">
        <x-admin.field label="Tienda que recibe" name="to_branch_id">
            <select name="to_branch_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach($destinations as $b)<option value="{{ $b->id }}" @selected(old('to_branch_id') == $b->id)>{{ $b->name }}</option>@endforeach
            </select>
        </x-admin.field>
        <x-admin.field label="Notas" name="notes"><input name="notes" value="{{ old('notes') }}" maxlength="255" placeholder="Ej.: va con el micro de las 3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></x-admin.field>
        <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-3 rounded-lg">Enviar</button>
    </div>
</form>
@endsection
