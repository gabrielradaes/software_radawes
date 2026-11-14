@extends('admin.layouts.app')
@section('title', 'Cliente: '.$customer->name)
@section('actions')<a href="{{ route('admin.clientes.index') }}" class="text-sm text-slate-600 hover:underline">← Clientes</a>@endsection

@section('content')
<div class="grid lg:grid-cols-3 gap-6">
    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <div class="flex items-center gap-4">
                <span class="w-14 h-14 rounded-full bg-brand-100 text-brand-700 font-bold text-xl flex items-center justify-center">{{ $customer->initials() }}</span>
                <div><h2 class="font-bold text-lg leading-tight">{{ $customer->name }}</h2><p class="text-xs text-slate-500">{{ $customer->password ? 'Cuenta web' : 'Creado por el equipo' }} · desde {{ $customer->created_at->isoFormat('D MMM YYYY') }}</p></div>
            </div>
            <dl class="mt-5 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Celular</dt><dd><a href="{{ $customer->whatsappLink('¡Hola '.$customer->firstName().'! Te escribimos de RadaWes.') }}" target="_blank" class="text-wa-500 font-semibold hover:underline">📱 {{ $customer->phone }}</a></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Correo</dt><dd class="truncate">{{ $customer->email ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Ciudad</dt><dd>{{ $customer->city ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">CI / NIT</dt><dd>{{ $customer->document ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Nacimiento</dt><dd>{{ $customer->birthdate?->isoFormat('D MMM YYYY') ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Último acceso</dt><dd>{{ $customer->last_login_at?->diffForHumans() ?? '—' }}</dd></div>
            </dl>
        </div>
        @can('customers.manage')
            <form method="POST" action="{{ route('admin.clientes.update', $customer) }}" class="bg-white rounded-xl border border-slate-200 p-6 space-y-3">
                @csrf @method('PATCH')
                <h3 class="font-semibold">Notas internas</h3>
                <textarea name="notes" rows="4" placeholder="Talla habitual, preferencias, observaciones…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $customer->notes) }}</textarea>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($customer->is_active) class="rounded"> Cuenta activa (puede ingresar y comentar)</label>
                <button class="bg-brand-900 text-white text-sm font-semibold px-4 py-2 rounded-lg">Guardar</button>
            </form>
        @elseif($customer->notes)
            <div class="bg-white rounded-xl border border-slate-200 p-6"><h3 class="font-semibold mb-2">Notas internas</h3><p class="text-sm whitespace-pre-line">{{ $customer->notes }}</p></div>
        @endcan
    </div>

    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl border border-slate-200">
            <div class="px-5 py-4 border-b border-slate-100 font-semibold">Consultas por WhatsApp ({{ $customer->whatsappLeads->count() }})</div>
            <ul class="divide-y divide-slate-100">
                @forelse($customer->whatsappLeads as $l)
                    <li class="px-5 py-3 text-sm flex items-center gap-3">
                        @if($l->product?->primaryImage)<img src="{{ $l->product->primaryImage->url() }}" class="w-10 h-10 rounded object-cover">@endif
                        <div class="flex-1 min-w-0"><p class="font-medium truncate">{{ $l->product?->name ?? ($l->post?->title ?? 'Consulta general') }} @if($l->variant)<span class="text-slate-500 font-normal">· {{ $l->variant->label() }}</span>@endif</p><p class="text-xs text-slate-400">{{ $l->created_at->isoFormat('D MMM YYYY HH:mm') }} · {{ $l->branch->name }} · {{ $l->source }}</p></div>
                        <span class="text-xs capitalize px-2 py-0.5 rounded-full bg-slate-100">{{ $l->status }}</span>
                    </li>
                @empty<li class="px-5 py-6 text-sm text-slate-400">Sin consultas.</li>@endforelse
            </ul>
        </div>
        <div class="bg-white rounded-xl border border-slate-200">
            <div class="px-5 py-4 border-b border-slate-100 font-semibold">Comentarios ({{ $customer->comments->count() }})</div>
            <ul class="divide-y divide-slate-100">
                @forelse($customer->comments as $c)
                    <li class="px-5 py-3 text-sm"><p class="text-xs text-slate-400">en {{ $c->commentable?->title ?? $c->commentable?->name ?? '—' }} · {{ $c->created_at->diffForHumans() }} {{ $c->is_approved ? '' : '· oculto' }}</p><p>{{ $c->body }}</p></li>
                @empty<li class="px-5 py-6 text-sm text-slate-400">Sin comentarios.</li>@endforelse
            </ul>
        </div>
        <div class="grid md:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-slate-200">
                <div class="px-5 py-4 border-b border-slate-100 font-semibold">Favoritos ({{ $customer->likes->count() }})</div>
                <ul class="divide-y divide-slate-100">
                    @forelse($customer->likes as $l)
                        <li class="px-5 py-2.5 text-sm">{{ $l->likeable instanceof \App\Models\Product ? '👞' : '📣' }} {{ $l->likeable?->name ?? $l->likeable?->title ?? '—' }}</li>
                    @empty<li class="px-5 py-6 text-sm text-slate-400">Sin favoritos.</li>@endforelse
                </ul>
            </div>
            <div class="bg-white rounded-xl border border-slate-200">
                <div class="px-5 py-4 border-b border-slate-100 font-semibold">Compras ({{ $customer->sales->count() }})</div>
                <ul class="divide-y divide-slate-100">
                    @forelse($customer->sales as $s)
                        <li class="px-5 py-2.5 text-sm flex justify-between"><span>{{ $s->code }} <span class="text-xs text-slate-400">{{ $s->sold_at->isoFormat('D MMM YYYY') }}</span></span><span class="font-semibold">{{ money($s->total) }}</span></li>
                    @empty<li class="px-5 py-6 text-sm text-slate-400">Sin compras registradas.</li>@endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
