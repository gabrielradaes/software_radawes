@extends('admin.layouts.app')
@section('title', 'Panel')

@section('content')
@php
    $u = auth()->user();
    $quick = array_values(array_filter([
        $u->can('sales.create') ? ['🧾', 'Vender', 'Registrar una venta', route('admin.ventas.create'), 'bg-emerald-600 hover:bg-emerald-700 text-white'] : null,
        $u->can('stock.view') ? ['🔎', 'Ver stock', '¿Hay talla? Buscar modelo', route('admin.stock.index'), 'bg-white hover:bg-slate-50'] : null,
        $u->can('posts.manage') ? ['📣', 'Publicar', 'Fotos, videos y ofertas', route('admin.publicaciones.create'), 'bg-gradient-to-br from-fuchsia-600 to-orange-500 text-white'] : null,
        $u->can('sales.create') ? ['📌', 'Apartar', 'Separar un par', route('admin.apartados.create'), 'bg-white hover:bg-slate-50'] : null,
        $u->can('sales.create') ? ['💵', 'Caja', 'Abrir, cerrar, movimientos', route('admin.caja'), 'bg-white hover:bg-slate-50'] : null,
        $u->can('stock.adjust') ? ['🚚', 'Llegó mercadería', 'Registrar una compra', route('admin.compras.create'), 'bg-white hover:bg-slate-50'] : null,
        $u->can('products.manage') ? ['👞', 'Nuevo producto', 'Crear un modelo', route('admin.productos.create'), 'bg-white hover:bg-slate-50'] : null,
        $u->can('customers.view') ? ['🤝', 'Clientes', 'Seguimientos y avisos', route('admin.crm'), 'bg-white hover:bg-slate-50'] : null,
        $u->can('activity.view') ? ['🕵️', 'Historial', 'Qué hizo el equipo', route('admin.auditoria'), 'bg-white hover:bg-slate-50'] : ['🗒️', 'Mi actividad', 'Lo que hice', route('admin.mi-actividad'), 'bg-white hover:bg-slate-50'],
    ]));
@endphp
<div class="mb-6">
    <p class="text-slate-600 mb-3">Hola, <strong>{{ $u->firstName() }}</strong> 👋 ¿Qué quieres hacer?</p>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        @foreach($quick as [$icon, $label, $sub, $url, $cls])
            <a href="{{ $url }}" class="rounded-xl border border-slate-200 p-4 shadow-sm transition {{ $cls }}">
                <span class="text-2xl">{{ $icon }}</span>
                <p class="font-bold mt-1">{{ $label }}</p>
                <p class="text-xs opacity-75">{{ $sub }}</p>
            </a>
        @endforeach
    </div>
</div>
@php
    $cards = [
        ['Leads nuevos', $stats['leads_nuevos'], 'por contactar', route('admin.leads.index', ['estado' => 'nuevo']), 'bg-emerald-500'],
        ['Consultas esta semana', $stats['leads_semana'], 'clics en WhatsApp', route('admin.leads.index'), 'bg-brand-500'],
        ['Stock bajo', $stats['stock_bajo'], 'variantes bajo el mínimo', auth()->user()->can('stock.view') ? route('admin.stock.index', ['bajo' => 1]) : null, 'bg-amber-500'],
        ['Comentarios (7 días)', $stats['comentarios_semana'], $stats['comentarios_pendientes'].' pendientes', auth()->user()->can('comments.moderate') ? route('admin.comentarios.index') : null, 'bg-sky-500'],
        ['Me gusta (7 días)', $stats['likes_semana'], 'en productos y posts', null, 'bg-rose-500'],
        ['Productos publicados', $stats['productos'], $stats['posts'].' publicaciones', auth()->user()->can('products.manage') ? route('admin.productos.index') : null, 'bg-slate-700'],
    ];
@endphp
<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
    @foreach($cards as [$label, $value, $sub, $url, $color])
        <a href="{{ $url ?? '#' }}" class="bg-white rounded-xl border border-slate-200 p-4 hover:shadow transition {{ $url ? '' : 'pointer-events-none' }}">
            <div class="flex items-center justify-between"><p class="text-xs text-slate-500">{{ $label }}</p><span class="w-2 h-2 rounded-full {{ $color }}"></span></div>
            <p class="text-3xl font-bold mt-1">{{ $value }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ $sub }}</p>
        </a>
    @endforeach
</div>

@if($byBranch->count())
<div class="bg-white rounded-xl border border-slate-200 mt-6 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <h2 class="font-semibold">Rendimiento por tienda · últimos 30 días</h2>
        <span class="text-xs text-slate-500">Compara qué página mueve más publicaciones y consultas</span>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-5 py-2">Tienda</th><th class="px-3 py-2 text-center">Productos</th><th class="px-3 py-2 text-center">Stock</th><th class="px-3 py-2 text-center">Publicaciones</th><th class="px-3 py-2 text-center">♥ en posts</th><th class="px-3 py-2 text-center">💬</th><th class="px-3 py-2 text-center">Consultas WA</th><th class="px-3 py-2 text-center">Vendidos</th><th class="px-3 py-2"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @foreach($byBranch as $r)
                <tr>
                    <td class="px-5 py-2.5"><span class="inline-flex items-center gap-2 font-semibold"><span class="w-3 h-3 rounded-full" style="background: {{ $r['branch']->color_primary }}"></span>{{ $r['branch']->brandName() }} <span class="text-slate-400 font-normal">· {{ $r['branch']->city }}</span></span></td>
                    <td class="px-3 py-2.5 text-center">{{ $r['productos'] }}</td>
                    <td class="px-3 py-2.5 text-center">{{ $r['stock'] }}</td>
                    <td class="px-3 py-2.5 text-center">{{ $r['posts'] }}</td>
                    <td class="px-3 py-2.5 text-center text-rose-600 font-semibold">{{ $r['likes'] }}</td>
                    <td class="px-3 py-2.5 text-center">{{ $r['comentarios'] }}</td>
                    <td class="px-3 py-2.5 text-center text-emerald-700 font-semibold">{{ $r['leads'] }}</td>
                    <td class="px-3 py-2.5 text-center">{{ $r['vendidos'] }}</td>
                    <td class="px-3 py-2.5 text-right text-xs"><a href="{{ route('home', $r['branch']) }}" target="_blank" class="text-brand-500 hover:underline">Ver página ↗</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="grid lg:grid-cols-3 gap-6 mt-6">
    {{-- Gráfico leads --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 lg:col-span-2">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold">Consultas por WhatsApp · últimos 14 días</h2>
            <span class="text-sm text-slate-500">{{ $chart->sum('n') }} en total</span>
        </div>
        @php $max = max(1, $chart->max('n')); @endphp
        <div class="flex items-end gap-1.5 h-40">
            @foreach($chart as $p)
                <div class="flex-1 flex flex-col items-center gap-1 group" title="{{ \Carbon\Carbon::parse($p['d'])->isoFormat('D MMM') }}: {{ $p['n'] }}">
                    <span class="text-[10px] text-slate-500 opacity-0 group-hover:opacity-100">{{ $p['n'] }}</span>
                    <div class="w-full rounded-t bg-emerald-500/80 group-hover:bg-emerald-600 transition-all" style="height: {{ max(4, round($p['n'] / $max * 120)) }}px"></div>
                    <span class="text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($p['d'])->format('d') }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Top --}}
    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="font-semibold mb-3">Más consultados</h2>
            <ul class="space-y-2">
                @forelse($topConsultados as $t)
                    <li class="flex items-center gap-3 text-sm">
                        @if($t->product?->primaryImage)<img src="{{ $t->product->primaryImage->url() }}" class="w-9 h-9 rounded object-cover">@endif
                        <span class="flex-1 truncate">{{ $t->product?->name ?? '—' }}</span>
                        <span class="font-bold text-emerald-600">{{ $t->total }}</span>
                    </li>
                @empty
                    <li class="text-sm text-slate-400">Aún sin consultas.</li>
                @endforelse
            </ul>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="font-semibold mb-3">Más gustados</h2>
            <ul class="space-y-2">
                @foreach($topGustados as $p)
                    <li class="flex items-center gap-3 text-sm">
                        @if($p->primaryImage)<img src="{{ $p->primaryImage->url() }}" class="w-9 h-9 rounded object-cover">@endif
                        <span class="flex-1 truncate">{{ $p->name }}</span>
                        <span class="font-bold text-rose-500">♥ {{ $p->likes_count }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6 mt-6">
    <div class="bg-white rounded-xl border border-slate-200">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-semibold">Últimas consultas</h2>
            @can('leads.manage')<a href="{{ route('admin.leads.index') }}" class="text-sm text-brand-500 hover:underline">Ver todas</a>@endcan
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($leads as $l)
                <li class="px-5 py-3 text-sm flex items-center gap-3">
                    <span class="w-2 h-2 rounded-full shrink-0 {{ ['nuevo' => 'bg-emerald-500', 'contactado' => 'bg-sky-500', 'vendido' => 'bg-brand-500', 'perdido' => 'bg-slate-300'][$l->status] }}"></span>
                    <div class="flex-1 min-w-0">
                        <p class="truncate font-medium">{{ $l->product?->name ?? ($l->post?->title ?? 'Consulta general') }} @if($l->variant)<span class="text-slate-500 font-normal">· {{ $l->variant->label() }}</span>@endif</p>
                        <p class="text-xs text-slate-400">{{ $l->created_at->diffForHumans() }} · origen: {{ $l->source }}</p>
                    </div>
                    <span class="text-xs capitalize px-2 py-0.5 rounded-full bg-slate-100">{{ $l->status }}</span>
                </li>
            @empty
                <li class="px-5 py-6 text-sm text-slate-400">Sin consultas todavía.</li>
            @endforelse
        </ul>
    </div>
    <div class="bg-white rounded-xl border border-slate-200">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-semibold">Últimos comentarios</h2>
            @can('comments.moderate')<a href="{{ route('admin.comentarios.index') }}" class="text-sm text-brand-500 hover:underline">Moderar</a>@endcan
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($comments as $c)
                <li class="px-5 py-3 text-sm">
                    <p><span class="font-semibold">{{ $c->author_name }}</span> <span class="text-slate-400 text-xs">en {{ $c->commentable?->title ?? $c->commentable?->name ?? '—' }} · {{ $c->created_at->diffForHumans() }}</span></p>
                    <p class="text-slate-700 mt-0.5">{{ \Illuminate\Support\Str::limit($c->body, 120) }}</p>
                </li>
            @empty
                <li class="px-5 py-6 text-sm text-slate-400">Sin comentarios.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
