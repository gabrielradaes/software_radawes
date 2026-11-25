@extends('admin.layouts.app')
@section('title', 'Publicaciones')
@section('actions')<a href="{{ route('admin.publicaciones.create') }}" class="bg-brand-900 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ Nueva publicación</a>@endsection

@section('content')
<form method="GET" class="mb-5 flex flex-wrap gap-2">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por título…" class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm">
    @if($branches->count())
        <select name="sucursal" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Todas las tiendas</option>
            @foreach($branches as $b)<option value="{{ $b->id }}" @selected(request('sucursal') == $b->id)>{{ $b->name }}</option>@endforeach
        </select>
    @endif
</form>
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-4 py-3">Publicación</th><th class="px-4 py-3">Tipo</th><th class="px-4 py-3">Sucursal</th><th class="px-4 py-3 text-center">♥</th><th class="px-4 py-3 text-center">💬</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($posts as $p)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-lg bg-slate-100 overflow-hidden shrink-0 flex items-center justify-center">
                                @if($img = $p->media->firstWhere('type', 'imagen'))<img src="{{ $img->url() }}" class="w-full h-full object-cover">
                                @elseif($p->media->firstWhere('type', 'video'))<span class="text-slate-500">▶</span>@endif
                            </div>
                            <div class="min-w-0">
                                <a href="{{ route('admin.publicaciones.edit', $p) }}" class="font-semibold hover:text-brand-500">{{ $p->is_pinned ? '📌 ' : '' }}{{ $p->title ?: \Illuminate\Support\Str::limit($p->body, 50) }}</a>
                                <p class="text-xs text-slate-400">{{ $p->author?->name }} · {{ $p->published_at?->isoFormat('D MMM YYYY, HH:mm') ?? 'sin publicar' }} @if($p->product) · 🏷 {{ $p->product->name }} @endif</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 capitalize">{{ $p->type }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $p->branch?->name ?? 'Todas' }}</td>
                    <td class="px-4 py-3 text-center">{{ $p->likes_count }}</td>
                    <td class="px-4 py-3 text-center">{{ $p->comments_count }}</td>
                    <td class="px-4 py-3">{!! $p->published_at && $p->published_at->isPast() ? '<span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">Publicada</span>' : ($p->published_at ? '<span class="text-xs px-2 py-0.5 rounded-full bg-sky-100 text-sky-800">Programada</span>' : '<span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Borrador</span>') !!}</td>
                    <td class="px-4 py-3 text-right text-xs whitespace-nowrap">
                        <a href="{{ route('feed.show', ['branch' => $p->branch?->slug ?? $currentBranch->slug, 'post' => $p]) }}" target="_blank" class="text-slate-500 hover:underline">Ver ↗</a>
                        <a href="{{ route('admin.publicaciones.edit', $p) }}" class="ml-3 text-slate-600 hover:underline">Editar</a>
                        @can('records.delete')<form method="POST" action="{{ route('admin.publicaciones.destroy', $p) }}" class="inline ml-3" onsubmit="return confirmar('¿Eliminar esta publicación?')">@csrf @method('DELETE')<button class="text-rose-600 hover:underline">Eliminar</button></form>@endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Sin publicaciones.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $posts->links() }}</div>
@endsection
