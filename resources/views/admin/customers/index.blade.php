@extends('admin.layouts.app')
@section('title', 'Clientes')
@section('actions')<a href="{{ route('admin.clientes.create') }}" class="bg-brand-900 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ Nuevo cliente</a>@endsection

@section('content')
<div class="grid grid-cols-3 gap-4 mb-5 max-w-xl">
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Clientes</p><p class="text-2xl font-bold">{{ $totals['total'] }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Con cuenta web</p><p class="text-2xl font-bold">{{ $totals['registrados'] }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Nuevos (7 días)</p><p class="text-2xl font-bold text-emerald-700">{{ $totals['semana'] }}</p></div>
</div>
<form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-5 flex flex-wrap gap-3">
    <input type="search" name="q" value="{{ $q }}" placeholder="Nombre, celular, correo o CI…" class="flex-1 min-w-[220px] rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <select name="filtro" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Todos</option>
        <option value="registrados" @selected(request('filtro') === 'registrados')>Con cuenta web</option>
        <option value="inactivos" @selected(request('filtro') === 'inactivos')>Desactivados</option>
    </select>
    <button class="bg-slate-800 text-white text-sm px-4 py-2 rounded-lg">Buscar</button>
</form>
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-4 py-3">Cliente</th><th class="px-4 py-3">Contacto</th><th class="px-4 py-3 text-center">❤️</th><th class="px-4 py-3 text-center">💬</th><th class="px-4 py-3 text-center">WhatsApp</th><th class="px-4 py-3 text-center">Compras</th><th class="px-4 py-3">Registro</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($customers as $c)
                <tr class="hover:bg-slate-50 {{ $c->is_active ? '' : 'opacity-50' }}">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="w-9 h-9 rounded-full bg-brand-100 text-brand-700 font-bold flex items-center justify-center">{{ $c->initials() }}</span>
                            <div><a href="{{ route('admin.clientes.show', $c) }}" class="font-semibold hover:text-brand-500">{{ $c->name }}</a><p class="text-xs text-slate-400">{{ $c->city }} @if($c->document)· CI {{ $c->document }}@endif @unless($c->password)· <span class="text-amber-700">sin cuenta web</span>@endunless @if($c->password && ! $c->email_verified_at)· <span class="text-rose-600">Gmail sin verificar</span>@elseif($c->email_verified_at)· <span class="text-emerald-700">✓ Gmail verificado</span>@endif</p></div>
                        </div>
                    </td>
                    <td class="px-4 py-3"><a href="{{ $c->whatsappLink() }}" target="_blank" class="text-wa-500 font-semibold hover:underline">📱 {{ $c->phone }}</a><p class="text-xs text-slate-500">{{ $c->email }}</p></td>
                    <td class="px-4 py-3 text-center">{{ $c->likes_count }}</td>
                    <td class="px-4 py-3 text-center">{{ $c->comments_count }}</td>
                    <td class="px-4 py-3 text-center">{{ $c->whatsapp_leads_count }}</td>
                    <td class="px-4 py-3 text-center">{{ $c->sales_count }}</td>
                    <td class="px-4 py-3 text-slate-500 text-xs">{{ $c->created_at->isoFormat('D MMM YYYY') }}<br>último acceso: {{ $c->last_login_at?->diffForHumans() ?? '—' }}</td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('admin.clientes.show', $c) }}" class="text-xs font-semibold text-brand-500 hover:underline">Ver ficha</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Aún no hay clientes. Aparecerán cuando se registren en el sitio o cuando registres una venta con sus datos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $customers->links() }}</div>
@endsection
