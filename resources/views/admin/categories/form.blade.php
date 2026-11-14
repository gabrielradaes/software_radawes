@extends('admin.layouts.app')
@section('title', $category->exists ? 'Editar categoría' : 'Nueva categoría')

@section('content')
<form method="POST" action="{{ $category->exists ? route('admin.categorias.update', $category) : route('admin.categorias.store') }}" class="bg-white rounded-xl border border-slate-200 p-6 max-w-xl space-y-4">
    @csrf @if($category->exists) @method('PUT') @endif
    <x-admin.field label="Nombre" name="name"><input name="name" value="{{ old('name', $category->name) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
    <x-admin.field label="Pertenece a" name="parent_id" help="Elige Calzado para crear una clase de calzado (montañeras, zapatenis…). Vacío = categoría principal del menú.">
        <select name="parent_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            <option value="">— Ninguna (principal) —</option>
            @foreach($parents as $p)<option value="{{ $p->id }}" @selected(old('parent_id', $category->parent_id) == $p->id)>{{ $p->name }}</option>@endforeach
        </select>
    </x-admin.field>
    <x-admin.field label="Descripción" name="description"><textarea name="description" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('description', $category->description) }}</textarea></x-admin.field>
    <div class="grid grid-cols-2 gap-4 items-end">
        <x-admin.field label="Orden" name="sort_order"><input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
        <label class="flex items-center gap-2 text-sm pb-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active)) class="rounded"> Activa</label>
    </div>
    <div class="flex gap-3 pt-2">
        <button class="bg-brand-900 hover:bg-brand-700 text-white font-semibold px-5 py-2.5 rounded-lg">Guardar</button>
        <a href="{{ route('admin.categorias.index') }}" class="px-5 py-2.5 text-sm text-slate-500 hover:underline">Cancelar</a>
    </div>
</form>
@endsection
