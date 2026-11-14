@extends('admin.layouts.app')
@section('title', 'Comentarios')

@section('content')
<div class="flex gap-2 mb-5 text-sm">
    @foreach(['todos' => 'Todos', 'sin-respuesta' => 'Sin respuesta del equipo', 'pendientes' => 'Ocultos'] as $k => $v)
        <a href="{{ route('admin.comentarios.index', ['filtro' => $k]) }}" class="px-3 py-1.5 rounded-full border {{ $filter === $k ? 'bg-brand-900 text-white border-brand-900' : 'bg-white border-slate-200 hover:bg-slate-50' }}">{{ $v }}</a>
    @endforeach
</div>
<div class="space-y-4 max-w-3xl">
    @forelse($comments as $c)
        <div class="bg-white rounded-xl border border-slate-200 p-5 {{ $c->is_approved ? '' : 'opacity-70 border-dashed' }}" x-data="{ reply: false }">
            <div class="flex items-start gap-3">
                <span class="w-9 h-9 rounded-full bg-brand-100 text-brand-700 font-bold flex items-center justify-center shrink-0">{{ mb_strtoupper(mb_substr($c->author_name, 0, 1)) }}</span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm"><span class="font-semibold">{{ $c->author_name }}</span>
                        @if($c->author_phone)<a href="{{ whatsapp_link($c->author_phone) }}" target="_blank" class="text-wa-500 text-xs ml-1">📱 {{ $c->author_phone }}</a>@endif
                        <span class="text-slate-400 text-xs">· {{ $c->created_at->diffForHumans() }}</span>
                    </p>
                    <p class="text-xs text-slate-500">en
                        @if($c->commentable instanceof \App\Models\Post)<a href="{{ route('feed.show', ['branch' => $c->commentable->branch?->slug ?? $currentBranch->slug, 'post' => $c->commentable]) }}" target="_blank" class="hover:underline">📣 {{ $c->commentable->title }}</a>
                        @elseif($c->commentable)<a href="{{ route('products.show', ['branch' => $c->commentable->branches->first()?->slug ?? $currentBranch->slug, 'product' => $c->commentable]) }}" target="_blank" class="hover:underline">👞 {{ $c->commentable->name }}</a>
                        @else — @endif
                    </p>
                    <p class="mt-2 text-sm">{{ $c->body }}</p>
                    @foreach($c->replies as $r)
                        <div class="mt-3 ml-4 pl-3 border-l-2 border-brand-200 text-sm">
                            <p><span class="font-semibold">{{ $r->author_name }}</span> @if($r->isStaff())<span class="text-[10px] bg-gold-400/40 px-1.5 rounded uppercase">Equipo</span>@endif <span class="text-slate-400 text-xs">· {{ $r->created_at->diffForHumans() }}</span></p>
                            <p class="text-slate-700">{{ $r->body }}</p>
                        </div>
                    @endforeach
                    <div class="mt-3 flex gap-4 text-xs">
                        <button @click="reply = !reply" class="font-semibold text-brand-500 hover:underline">Responder</button>
                        <form method="POST" action="{{ route('admin.comentarios.toggle', $c) }}">@csrf @method('PATCH')<button class="text-slate-500 hover:underline">{{ $c->is_approved ? 'Ocultar' : 'Aprobar' }}</button></form>
                        <form method="POST" action="{{ route('admin.comentarios.destroy', $c) }}" onsubmit="return confirmar('¿Eliminar comentario y sus respuestas?')">@csrf @method('DELETE')<button class="text-rose-600 hover:underline">Eliminar</button></form>
                    </div>
                    <form x-show="reply" x-cloak method="POST" action="{{ route('admin.comentarios.reply', $c) }}" class="mt-3 flex gap-2">
                        @csrf
                        <input name="body" required maxlength="1000" placeholder="Escribe la respuesta oficial…" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="bg-brand-900 text-white text-sm px-4 rounded-lg">Enviar</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <p class="text-slate-400 text-sm">No hay comentarios en este filtro.</p>
    @endforelse
</div>
<div class="mt-4">{{ $comments->links() }}</div>
@endsection
