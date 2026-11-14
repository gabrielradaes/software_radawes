@extends('admin.layouts.app')
@section('title', $user->exists ? 'Editar usuario' : 'Nuevo usuario')

@section('content')
<form method="POST" action="{{ $user->exists ? route('admin.usuarios.update', $user) : route('admin.usuarios.store') }}" class="bg-white rounded-xl border border-slate-200 p-6 max-w-xl space-y-4">
    @csrf @if($user->exists) @method('PUT') @endif
    <x-admin.field label="Nombre" name="name"><input name="name" value="{{ old('name', $user->name) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
    <div class="grid sm:grid-cols-2 gap-4">
        <x-admin.field label="Correo (para ingresar)" name="email"><input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
        <x-admin.field label="Celular" name="phone"><input name="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
        <x-admin.field label="Rol" name="role_id">
            <select name="role_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @foreach($roles as $r)<option value="{{ $r->id }}" @selected(old('role_id', $user->role_id) == $r->id)>{{ $r->label }}</option>@endforeach
            </select>
        </x-admin.field>
        <x-admin.field label="Sucursal" name="branch_id" help="Supervisores y empleados solo ven su sucursal">
            <select name="branch_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="">Todas (solo admin)</option>
                @foreach($branches as $b)<option value="{{ $b->id }}" @selected(old('branch_id', $user->branch_id) == $b->id)>{{ $b->name }}</option>@endforeach
            </select>
        </x-admin.field>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
        <x-admin.field label="{{ $user->exists ? 'Nueva contraseña (opcional)' : 'Contraseña' }}" name="password"><input type="password" name="password" {{ $user->exists ? '' : 'required' }} minlength="8" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
        <x-admin.field label="Repetir contraseña" name="password_confirmation"><input type="password" name="password_confirmation" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="rounded"> Activo (puede ingresar)</label>
    <div class="flex gap-3 pt-2">
        <button class="bg-brand-900 hover:bg-brand-700 text-white font-semibold px-5 py-2.5 rounded-lg">Guardar</button>
        <a href="{{ route('admin.usuarios.index') }}" class="px-5 py-2.5 text-sm text-slate-500 hover:underline">Cancelar</a>
    </div>
</form>
@endsection
