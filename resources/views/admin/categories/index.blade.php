@extends('admin.layouts.app')
@section('title', 'Categorías y clases de calzado')
@section('actions')<a href="{{ route('admin.categorias.create') }}" class="bg-brand-900 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ Nueva</a>@endsection

@section('content')
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden max-w-3xl">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-4 py-3">Categoría / clase</th><th class="px-4 py-3 text-center">Productos</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @foreach($categories as $c)
                @foreach([$c, ...$c->children] as $cat)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2.5 {{ $cat->parent_id ? 'pl-10 text-slate-600' : 'font-semibold' }}">{{ $cat->parent_id ? '↳ ' : '' }}{{ $cat->name }} <span class="text-xs text-slate-400 font-normal">/{{ $cat->slug }}</span></td>
                        <td class="px-4 py-2.5 text-center">{{ $cat->products_count }}</td>
                        <td class="px-4 py-2.5">{!! $cat->is_active ? '<span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">Activa</span>' : '<span class="text-xs px-2 py-0.5 rounded-full bg-slate-200">Oculta</span>' !!}</td>
                        <td class="px-4 py-2.5 text-right text-xs whitespace-nowrap">
                            <a href="{{ route('admin.categorias.edit', $cat) }}" class="text-slate-600 hover:underline">Editar</a>
                            <form method="POST" action="{{ route('admin.categorias.destroy', $cat) }}" class="inline ml-3" onsubmit="return confirmar('¿Eliminar {{ $cat->name }}?')">@csrf @method('DELETE')<button class="text-rose-600 hover:underline">Eliminar</button></form>
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</div>
@endsection
