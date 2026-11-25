@extends('layouts.app')
@section('title', 'Mi cuenta · '.$currentBranch->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 py-10" x-data="{ tab: @js(request('tab', 'favoritos')) }">
    <div class="flex flex-wrap items-center gap-4 mb-8">
        <span class="w-16 h-16 rounded-full bg-brand-900 text-gold-400 font-display font-bold text-2xl flex items-center justify-center">{{ $customer->initials() }}</span>
        <div class="flex-1">
            <p class="text-xs uppercase tracking-[0.2em] text-brand-500 font-semibold">Mi cuenta</p>
            <h1 class="font-display text-3xl font-bold">{{ $customer->name }}</h1>
            <p class="text-sm text-brand-700">{{ $customer->email }} · +591 {{ $customer->phone }} @if($customer->city) · {{ $customer->city }} @endif</p>
        </div>
        <form method="POST" action="{{ route('customer.logout') }}">@csrf<button class="text-sm text-brand-500 hover:text-brand-900 border border-brand-200 px-4 py-2 rounded-full">Cerrar sesión</button></form>
    </div>

    @if($errors->any())
        <div class="mb-5 bg-rose-50 border border-rose-200 text-rose-800 text-sm px-4 py-3 rounded-xl"><ul class="list-disc ml-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="flex gap-2 overflow-x-auto no-scrollbar mb-6 text-sm">
        @foreach(['pedidos' => '🛍️ Mis pedidos ('.$orders->where('status', 'activo')->count().')', 'favoritos' => '❤️ Favoritos ('.$favorites->count().')', 'consultas' => '📱 Mis consultas ('.$leads->count().')', 'comentarios' => '💬 Mis comentarios ('.$comments->count().')', 'compras' => '🧾 Mis compras ('.$sales->count().')', 'actividad' => '🗒️ Mi actividad', 'datos' => '⚙️ Mis datos'] as $k => $label)
            <button @click="tab = @js($k)" :class="tab === @js($k) ? 'bg-brand-900 text-white border-brand-900' : 'bg-white border-brand-200 hover:bg-brand-50'" class="shrink-0 px-4 py-2 rounded-full border font-medium transition">{{ $label }}</button>
        @endforeach
    </div>

    {{-- Pedidos separados / apartados --}}
    <section x-show="tab === 'pedidos'" x-cloak class="space-y-3 max-w-3xl">
        @forelse($orders as $o)
            <div class="bg-white rounded-2xl border border-brand-100 p-4">
                <div class="flex flex-wrap justify-between gap-2">
                    <p class="font-semibold">Pedido {{ $o->code }} · {{ $o->branch->name }}</p>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ ['activo' => $o->isExpired() ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-800', 'retirado' => 'bg-brand-100 text-brand-800', 'cancelado' => 'bg-slate-100 text-slate-500'][$o->status] ?? 'bg-slate-100' }}">
                        {{ $o->status === 'activo' ? ($o->isExpired() ? 'Venció' : 'Te lo guardamos hasta el '.$o->expires_at->isoFormat('D MMM')) : ucfirst($o->status) }}</span>
                </div>
                <ul class="mt-2 text-sm text-brand-700">
                    @foreach($o->items as $it)<li>{{ $it->quantity }} × {{ $it->variant->product->name }} · {{ $it->variant->label() }} — {{ money($it->unit_price) }}</li>@endforeach
                </ul>
                @if($o->status === 'activo')<p class="mt-2 text-sm">Pagas al retirar: <strong>{{ money($o->balance()) }}</strong></p>@endif
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-brand-100 p-8 text-center text-brand-500">Todavía no separaste ningún par. En cada producto elige tu talla y toca <strong>«Separar en tienda»</strong>.</div>
        @endforelse
    </section>

    {{-- Favoritos --}}
    <section x-show="tab === 'favoritos'" x-cloak>
        @if($favorites->count())
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                @foreach($favorites as $p)<x-product-card :product="$p"/>@endforeach
            </div>
        @else
            <div class="bg-white rounded-2xl border border-brand-100 p-10 text-center"><p class="font-display text-xl font-bold">Aún no tienes favoritos</p><p class="text-brand-500 text-sm mt-1">Toca el ❤️ en cualquier producto para guardarlo aquí.</p><a href="{{ route('catalog.index') }}" class="inline-block mt-4 bg-brand-900 text-white px-5 py-2 rounded-full text-sm font-semibold">Ver catálogo</a></div>
        @endif
    </section>

    {{-- Consultas --}}
    <section x-show="tab === 'consultas'" x-cloak class="space-y-3 max-w-3xl">
        @forelse($leads as $l)
            <div class="bg-white rounded-2xl border border-brand-100 p-4 flex items-center gap-4">
                @if($l->product?->primaryImage)<img src="{{ $l->product->primaryImage->url() }}" class="w-14 h-14 rounded-xl object-cover">@endif
                <div class="flex-1 min-w-0">
                    <p class="font-semibold truncate">{{ $l->product?->name ?? ($l->post?->title ?? 'Consulta general') }} @if($l->variant)<span class="text-brand-500 font-normal">· {{ $l->variant->label() }}</span>@endif</p>
                    <p class="text-xs text-brand-500">{{ $l->created_at->diffForHumans() }} · {{ $l->branch->name }}</p>
                </div>
                <span class="text-xs capitalize px-2.5 py-1 rounded-full {{ ['nuevo' => 'bg-emerald-100 text-emerald-800', 'contactado' => 'bg-sky-100 text-sky-800', 'vendido' => 'bg-brand-100 text-brand-800', 'perdido' => 'bg-slate-100'][$l->status] }}">{{ $l->status === 'nuevo' ? 'enviada' : $l->status }}</span>
                @if($l->product)<a href="{{ route('whatsapp', ['producto' => $l->product->slug, 'variante' => $l->product_variant_id, 'origen' => 'mi-cuenta']) }}" target="_blank" class="text-wa-600 text-xs font-semibold">Volver a escribir</a>@endif
            </div>
        @empty
            <p class="text-brand-500 text-sm">Todavía no has consultado por WhatsApp desde la página.</p>
        @endforelse
    </section>

    {{-- Comentarios --}}
    <section x-show="tab === 'comentarios'" x-cloak class="space-y-3 max-w-3xl">
        @forelse($comments as $c)
            <div class="bg-white rounded-2xl border border-brand-100 p-4">
                <p class="text-xs text-brand-500">
                    @if($c->commentable instanceof \App\Models\Post) en <a href="{{ route('feed.show', $c->commentable) }}#comentario-{{ $c->id }}" class="font-semibold text-brand-900 hover:underline">{{ $c->commentable->title }}</a>
                    @elseif($c->commentable) en <a href="{{ route('products.show', $c->commentable) }}#comentario-{{ $c->id }}" class="font-semibold text-brand-900 hover:underline">{{ $c->commentable->name }}</a>
                    @endif · {{ $c->created_at->diffForHumans() }}
                </p>
                <p class="mt-1 text-sm">{{ $c->body }}</p>
            </div>
        @empty
            <p class="text-brand-500 text-sm">Aún no has comentado.</p>
        @endforelse
    </section>

    {{-- Compras --}}
    <section x-show="tab === 'compras'" x-cloak class="space-y-3 max-w-3xl">
        @forelse($sales as $s)
            <div class="bg-white rounded-2xl border border-brand-100 p-4">
                <div class="flex justify-between text-sm"><span class="font-semibold">{{ $s->code }}</span><span class="text-brand-500">{{ $s->sold_at->isoFormat('D MMM YYYY') }} · {{ $s->branch->name }}</span></div>
                <ul class="mt-2 text-sm text-brand-800">@foreach($s->items as $i)<li>{{ $i->quantity }} × {{ $i->variant->product->name }} ({{ $i->variant->label() }}) — {{ money($i->line_total) }}</li>@endforeach</ul>
                <p class="mt-2 text-right font-bold">{{ money($s->total) }}</p>
            </div>
        @empty
            <p class="text-brand-500 text-sm">Cuando compres en tienda y nos des tu celular, tus compras aparecerán aquí.</p>
        @endforelse
    </section>

    {{-- Mi actividad --}}
    <section x-show="tab === 'actividad'" x-cloak class="max-w-3xl">
        <p class="text-sm text-brand-600 mb-3">Lo que hiciste en la página: ingresos a tu cuenta, pedidos, comentarios… Si ves algo que no hiciste, cambia tu contraseña.</p>
        <div class="bg-white rounded-2xl border border-brand-100 divide-y divide-brand-50">
            @forelse($activity as $a)
                <div class="px-4 py-3 flex gap-3 text-sm">
                    <span class="w-7 text-center">{{ $a->icon() }}</span>
                    <div class="flex-1 min-w-0"><p class="break-words">{{ $a->description }}</p><p class="text-xs text-brand-500">{{ $a->created_at->isoFormat('D MMM YYYY · HH:mm') }}@if($a->branch) · {{ $a->branch->name }}@endif</p></div>
                </div>
            @empty
                <p class="px-4 py-6 text-brand-500">Todavía no hay actividad.</p>
            @endforelse
        </div>
    </section>

    {{-- Datos --}}
    <section x-show="tab === 'datos'" x-cloak class="grid md:grid-cols-2 gap-6 max-w-4xl">
        <form method="POST" action="{{ route('customer.account.update') }}" class="bg-white rounded-2xl border border-brand-100 p-6 space-y-3">
            @csrf @method('PUT')
            <h2 class="font-display text-xl font-bold">Mis datos</h2>
            @foreach([['name', 'Nombre completo', 'text'], ['phone', 'Celular', 'text'], ['email', 'Gmail', 'email'], ['city', 'Ciudad', 'text'], ['document', 'CI / NIT (para facturas)', 'text'], ['birthdate', 'Fecha de nacimiento', 'date']] as [$f, $label, $type])
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $label }}</label>
                    <input type="{{ $type }}" name="{{ $f }}" value="{{ old($f, $f === 'birthdate' ? $customer->birthdate?->format('Y-m-d') : $customer->$f) }}" class="w-full rounded-xl border border-brand-200 px-3 py-2 text-sm">
                </div>
            @endforeach
            <button class="bg-brand-900 hover:bg-brand-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">Guardar</button>
        </form>
        <form method="POST" action="{{ route('customer.password') }}" class="bg-white rounded-2xl border border-brand-100 p-6 space-y-3 self-start">
            @csrf @method('PUT')
            <h2 class="font-display text-xl font-bold">Cambiar contraseña</h2>
            <div><label class="block text-sm font-medium mb-1">Contraseña actual</label><input type="password" name="current_password" required class="w-full rounded-xl border border-brand-200 px-3 py-2 text-sm"></div>
            <div><label class="block text-sm font-medium mb-1">Nueva contraseña</label><input type="password" name="password" required minlength="6" class="w-full rounded-xl border border-brand-200 px-3 py-2 text-sm"></div>
            <div><label class="block text-sm font-medium mb-1">Repetir nueva contraseña</label><input type="password" name="password_confirmation" required class="w-full rounded-xl border border-brand-200 px-3 py-2 text-sm"></div>
            <button class="bg-brand-900 hover:bg-brand-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">Cambiar</button>
        </form>
    </section>
</div>
@endsection
