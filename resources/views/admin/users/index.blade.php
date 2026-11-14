@extends('admin.layouts.app')
@section('title', 'Usuarios')
@section('actions')<a href="{{ route('admin.usuarios.create') }}" class="bg-brand-900 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ Nuevo usuario</a>@endsection

@section('content')
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden max-w-4xl">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-4 py-3">Usuario</th><th class="px-4 py-3">Rol</th><th class="px-4 py-3">Sucursal</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @foreach($users as $u)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="w-9 h-9 rounded-full bg-gold-500 text-brand-950 font-bold flex items-center justify-center">{{ $u->initials() }}</span>
                            <div><p class="font-semibold">{{ $u->name }} @if($u->id === auth()->id())<span class="text-xs text-slate-400">(tú)</span>@endif</p><p class="text-xs text-slate-400">{{ $u->email }} @if($u->phone)· {{ $u->phone }}@endif</p></div>
                        </div>
                    </td>
                    <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full {{ $u->role?->name === 'admin' ? 'bg-brand-900 text-white' : 'bg-slate-100' }}">{{ $u->role?->label ?? 'Sin rol' }}</span></td>
                    <td class="px-4 py-3 text-slate-600">{{ $u->branch?->name ?? 'Todas' }}</td>
                    <td class="px-4 py-3">{!! $u->is_active ? '<span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">Activo</span>' : '<span class="text-xs px-2 py-0.5 rounded-full bg-slate-200">Inactivo</span>' !!}</td>
                    <td class="px-4 py-3 text-right text-xs whitespace-nowrap">
                        <a href="{{ route('admin.usuarios.edit', $u) }}" class="text-slate-600 hover:underline">Editar</a>
                        @if($u->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.usuarios.destroy', $u) }}" class="inline ml-3" onsubmit="return confirmar('¿Eliminar a {{ $u->name }}?')">@csrf @method('DELETE')<button class="text-rose-600 hover:underline">Eliminar</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-6 max-w-4xl bg-white rounded-xl border border-slate-200 p-5">
    <h2 class="font-semibold mb-3">Qué puede hacer cada rol</h2>
    <div class="grid md:grid-cols-3 gap-4 text-sm">
        @foreach(\App\Models\Role::with('permissions')->get() as $r)
            <div><p class="font-semibold">{{ $r->label }}</p><p class="text-xs text-slate-500 mb-2">{{ $r->description }}</p>
                <ul class="text-xs text-slate-600 space-y-0.5">@foreach($r->permissions as $p)<li>✓ {{ $p->label }}</li>@endforeach</ul>
            </div>
        @endforeach
    </div>
</div>
@endsection
