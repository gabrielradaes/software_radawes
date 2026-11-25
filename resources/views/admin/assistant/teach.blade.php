@extends('admin.layouts.app')
@section('title', 'Enseñar al asistente')
@section('actions')
    <a href="{{ route('admin.asistente') }}" class="text-sm text-slate-600 hover:underline">← Volver al asistente</a>
@endsection

@section('content')
@php
    $u = auth()->user();
    $canRemove = fn ($ownerId) => $u->can('records.delete') || $ownerId === $u->id;
@endphp
<div class="max-w-5xl space-y-6">
    <div class="rounded-2xl bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-200 p-5">
        <p class="font-bold text-amber-900">🎓 Aquí le enseñas al asistente cómo hablan en tus tiendas</p>
        <p class="text-sm text-amber-900/80 mt-1">Mientras más le enseñen, más entiende. No cuesta nada y todo queda en tu sistema. También aprende solo cuando alguien toca <b>«¿Qué querías hacer?»</b> en el chat.</p>
    </div>

    {{-- Probar --}}
    <section class="bg-white rounded-2xl border border-slate-200 p-5">
        <h2 class="font-bold">🧪 Probar una frase</h2>
        <p class="text-sm text-slate-500">Escribe algo como lo diría el equipo y mira qué entiende (no guarda nada).</p>
        <form method="GET" class="mt-3 flex gap-2">
            <input name="probar" value="{{ $probe['text'] ?? '' }}" maxlength="500" placeholder="Ej.: cárgame 2 preto 39 del 596101-001" class="flex-1 rounded-lg border border-slate-300 px-3 py-2">
            <button class="bg-brand-900 hover:bg-brand-700 text-white text-sm font-semibold px-4 rounded-lg">Probar</button>
        </form>
        @if($probe)
            <div class="mt-4 rounded-xl border {{ $probe['intent'] ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }} p-4 text-sm">
                @if($probe['rewritten'])<p class="text-slate-600">Con tus palabras lo lee como: <i>«{{ $probe['rewritten'] }}»</i></p>@endif
                @if($probe['intent'])
                    <p class="font-semibold text-emerald-900">Entiende: {{ $probe['label'] }} <span class="font-normal text-emerald-800">({{ $probe['how'] }})</span></p>
                @else
                    <p class="font-semibold text-rose-900">No entiende qué acción es. Enséñale abajo en «Frases de ejemplo».</p>
                @endif
                @if($probe['data'])
                    <dl class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-2">
                        @foreach($probe['data'] as $k => $v)<div class="bg-white/70 rounded-lg px-2.5 py-1.5"><dt class="text-[11px] text-slate-500">{{ $k }}</dt><dd class="font-semibold">{{ $v }}</dd></div>@endforeach
                    </dl>
                @endif
            </div>
        @endif
    </section>

    {{-- No entendió --}}
    <section class="bg-white rounded-2xl border border-slate-200 p-5">
        <h2 class="font-bold">🤔 Lo que no entendió <span class="text-sm font-normal text-slate-500">(últimos 60 días)</span></h2>
        @forelse($unknown as $m)
            <form method="POST" action="{{ route('admin.asistente.ensenar.frase') }}" class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                @csrf
                <input type="hidden" name="text" value="{{ $m['text'] }}">
                <div class="flex-1 min-w-[220px]">
                    <p class="text-sm">«{{ \Illuminate\Support\Str::limit($m['text'], 180) }}»</p>
                    <p class="text-[11px] text-slate-400">{{ $m['user'] }} · {{ $m['when']->diffForHumans() }}</p>
                </div>
                <select name="intent" required class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                    <option value="">Quería…</option>
                    @foreach($intents as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                </select>
                <button class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold px-3 py-1.5 rounded-lg">Enseñar</button>
            </form>
        @empty
            <p class="text-sm text-slate-500 mt-2">Nada pendiente 👌</p>
        @endforelse
    </section>

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Palabras --}}
        <section class="bg-white rounded-2xl border border-slate-200 p-5" x-data="{ kind: 'color' }">
            <h2 class="font-bold">📖 Palabras del negocio</h2>
            <p class="text-sm text-slate-500">Ej.: <i>preto</i> = color Negro · <i>Demócrata</i> = marca · <i>urbano</i> = clase Casuales y urbanos · <i>depósito</i> = stock.</p>
            <form method="POST" action="{{ route('admin.asistente.ensenar.palabra') }}" class="mt-3 grid grid-cols-2 gap-2">
                @csrf
                <select name="kind" x-model="kind" class="rounded-lg border border-slate-300 px-2 py-2 text-sm col-span-2">
                    @foreach($kinds as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                </select>
                <input name="phrase" required maxlength="80" placeholder="Cuando dicen…" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="meaning" x-show="kind !== 'clase'" :required="kind !== 'clase'" maxlength="120" :placeholder="kind === 'color' ? 'es el color…' : (kind === 'marca' ? 'la marca se escribe…' : 'quiere decir…')" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="category_id" x-show="kind === 'clase'" x-cloak :required="kind === 'clase'" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                    <option value="">es la clase…</option>
                    @foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->parent_id ? '— ' : '' }}{{ $c->name }}</option>@endforeach
                </select>
                <button class="col-span-2 bg-brand-900 hover:bg-brand-700 text-white text-sm font-semibold py-2 rounded-lg">+ Enseñar palabra</button>
            </form>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse($words as $w)
                    <div class="flex items-center gap-2 py-2 text-sm">
                        <span class="text-[11px] uppercase tracking-wide bg-slate-100 text-slate-600 rounded px-1.5 py-0.5">{{ $kinds[$w->kind] ?? $w->kind }}</span>
                        <span class="flex-1 min-w-0 truncate"><b>{{ $w->phrase }}</b> → {{ $w->kind === 'clase' ? $w->category?->name : $w->meaning }}</span>
                        @if($canRemove($w->user_id))
                            <form method="POST" action="{{ route('admin.asistente.ensenar.palabra.quitar', $w) }}" onsubmit="return confirmar('¿Quitar «{{ $w->phrase }}»?')">@csrf @method('DELETE')<button class="text-rose-600 text-xs hover:underline">Quitar</button></form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500 py-2">Todavía no hay palabras.</p>
                @endforelse
            </div>
        </section>

        {{-- Frases --}}
        <section class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-bold">💬 Frases de ejemplo</h2>
            <p class="text-sm text-slate-500">Cómo piden cada cosa. Con 2 o 3 frases por acción ya reconoce las parecidas.</p>
            <form method="POST" action="{{ route('admin.asistente.ensenar.frase') }}" class="mt-3 space-y-2">
                @csrf
                <input name="text" required maxlength="300" placeholder="Ej.: cárgame esto al depósito" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <div class="flex gap-2">
                    <select name="intent" required class="flex-1 rounded-lg border border-slate-300 px-2 py-2 text-sm">
                        <option value="">quiere decir…</option>
                        @foreach($intents as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                    </select>
                    <button class="bg-brand-900 hover:bg-brand-700 text-white text-sm font-semibold px-4 rounded-lg">+ Enseñar</button>
                </div>
            </form>
            <div class="mt-4 space-y-3">
                @forelse($examples as $intent => $list)
                    <div>
                        <p class="text-xs font-semibold text-slate-500">{{ $intents[$intent] ?? $intent }} · {{ $list->count() }}</p>
                        @foreach($list->take(15) as $e)
                            <div class="flex items-center gap-2 py-1 text-sm">
                                <span class="flex-1 min-w-0 truncate">«{{ $e->text }}»</span>
                                <span class="text-[10px] text-slate-400">{{ $e->source === 'boton' ? 'del chat' : '' }}</span>
                                @if($canRemove($e->user_id))
                                    <form method="POST" action="{{ route('admin.asistente.ensenar.frase.quitar', $e) }}">@csrf @method('DELETE')<button class="text-rose-600 text-xs hover:underline">Quitar</button></form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Todavía no hay frases.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
