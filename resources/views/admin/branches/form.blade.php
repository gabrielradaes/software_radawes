@extends('admin.layouts.app')
@section('title', $branch->exists ? 'Editar: '.$branch->name : 'Nueva sucursal')

@section('content')
<form method="POST" action="{{ $branch->exists ? route('admin.sucursales.update', $branch) : route('admin.sucursales.store') }}" class="grid lg:grid-cols-3 gap-6"
      x-data="{ links: @js(old('links', $links ?: [['platform' => 'facebook', 'handle' => '', 'url' => '']])) }">
    @csrf @if($branch->exists) @method('PUT') @endif
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
            <div class="grid sm:grid-cols-2 gap-4">
                <x-admin.field label="Nombre" name="name"><input name="name" value="{{ old('name', $branch->name) }}" required placeholder="RadaWes El Alto" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
                <x-admin.field label="Lema" name="tagline"><input name="tagline" value="{{ old('tagline', $branch->tagline) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
            </div>
            <x-admin.field label="Descripción" name="description"><textarea name="description" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('description', $branch->description) }}</textarea></x-admin.field>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-admin.field label="Ciudad" name="city"><input name="city" value="{{ old('city', $branch->city) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
                <x-admin.field label="País" name="country"><input name="country" value="{{ old('country', $branch->country) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
            </div>
            <x-admin.field label="Dominio propio" name="domain" help="Quien entre por este dominio va directo a esta tienda. Local: radawes.localhost · Producción: radawes.com.bo (sin http ni www)">
                <input name="domain" value="{{ old('domain', $branch->domain) }}" placeholder="radawes.localhost" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
            </x-admin.field>
            <x-admin.field label="Dirección" name="address"><input name="address" value="{{ old('address', $branch->address) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
            <div class="grid sm:grid-cols-3 gap-4">
                <x-admin.field label="WhatsApp" name="whatsapp" help="Con código de país: 59177718360"><input name="whatsapp" value="{{ old('whatsapp', $branch->whatsapp) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
                <x-admin.field label="Teléfono" name="phone"><input name="phone" value="{{ old('phone', $branch->phone) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
                <x-admin.field label="Correo" name="email"><input type="email" name="email" value="{{ old('email', $branch->email) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
            </div>
            <x-admin.field label="Horario" name="opening_hours"><input name="opening_hours" value="{{ old('opening_hours', $branch->opening_hours) }}" placeholder="Lunes a sábado 9:00 – 19:30" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-admin.field label="Latitud" name="latitude" help="Para el mapa. En Google Maps: clic derecho → copiar coordenadas"><input name="latitude" value="{{ old('latitude', $branch->latitude) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
                <x-admin.field label="Longitud" name="longitude"><input name="longitude" value="{{ old('longitude', $branch->longitude) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></x-admin.field>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-3"><h2 class="font-semibold">Redes sociales</h2><button type="button" @click="links.push({platform: 'tiktok', handle: '', url: ''})" class="text-sm text-brand-500 font-semibold">+ Agregar</button></div>
            <template x-for="(l, i) in links" :key="i">
                <div class="grid grid-cols-[120px_1fr_2fr_auto] gap-2 mb-2">
                    <select :name="`links[${i}][platform]`" x-model="l.platform" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        <option value="facebook">Facebook</option><option value="instagram">Instagram</option><option value="tiktok">TikTok</option><option value="youtube">YouTube</option><option value="web">Web</option>
                    </select>
                    <input :name="`links[${i}][handle]`" x-model="l.handle" placeholder="@usuario" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                    <input :name="`links[${i}][url]`" x-model="l.url" placeholder="https://…" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                    <button type="button" @click="links.splice(i, 1)" class="text-rose-500 px-2">✕</button>
                </div>
            </template>
        </div>
    </div>
    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-3">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch->is_active)) class="rounded"> Activa (visible en el sitio)</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_main" value="1" @checked(old('is_main', $branch->is_main)) class="rounded"> Sucursal principal (se muestra por defecto)</label>
        </div>
        <button class="w-full bg-brand-900 hover:bg-brand-700 text-white font-semibold py-3 rounded-lg">Guardar</button>
        <a href="{{ route('admin.sucursales.index') }}" class="block text-center text-sm text-slate-500 hover:underline">Cancelar</a>
    </div>
</form>
@endsection
