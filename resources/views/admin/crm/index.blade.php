@extends('admin.layouts.app')
@section('title', 'CRM · Seguimientos')
@section('actions')<a href="{{ route('admin.clientes.create') }}" class="bg-brand-900 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ Nuevo cliente</a>@endsection

@section('content')
{{-- Embudo --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    @foreach(\App\Models\Customer::STAGES as $k => [$label, $class])
        <a href="{{ route('admin.clientes.index', ['etapa' => $k]) }}" class="bg-white rounded-xl border border-slate-200 p-4 hover:border-brand-400">
            <span class="text-[11px] px-2 py-0.5 rounded-full {{ $class }}">{{ $label }}</span>
            <p class="text-2xl font-bold mt-2">{{ $pipeline[$k] ?? 0 }}</p>
        </a>
    @endforeach
    <a href="{{ route('admin.clientes.index') }}" class="bg-white rounded-xl border border-rose-200 p-4" title="Compraron antes pero no en los últimos 90 días">
        <span class="text-[11px] px-2 py-0.5 rounded-full bg-rose-50 text-rose-700">Sin comprar 90 días</span>
        <p class="text-2xl font-bold mt-2 text-rose-700">{{ $inactive }}</p>
    </a>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        {{-- Nuevo seguimiento --}}
        <form method="POST" action="{{ route('admin.crm.tareas.store') }}" class="bg-white rounded-xl border border-slate-200 p-5 flex flex-wrap gap-2 items-end">
            @csrf
            <label class="flex-1 min-w-[220px] text-sm"><span class="text-xs text-slate-500">Nuevo seguimiento</span>
                <input name="title" required maxlength="200" placeholder="Ej.: Llamar a los clientes VIP por la nueva colección" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm"><span class="text-xs text-slate-500">Cuándo</span>
                <input type="datetime-local" name="due_at" required value="{{ now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i') }}" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            @if($staff->count() > 1)
                <label class="text-sm"><span class="text-xs text-slate-500">Responsable</span>
                    <select name="user_id" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach($staff as $s)<option value="{{ $s->id }}" @selected($s->id === auth()->id())>{{ $s->name }}</option>@endforeach
                    </select></label>
            @endif
            <button class="bg-brand-900 text-white text-sm font-semibold px-4 py-2 rounded-lg">Agendar</button>
        </form>

        @foreach($groups as $title => $tasks)
            <div class="bg-white rounded-xl border {{ $title === 'Atrasados' && $tasks->isNotEmpty() ? 'border-rose-300' : 'border-slate-200' }}">
                <div class="px-5 py-3 border-b border-slate-100 font-semibold {{ $title === 'Atrasados' && $tasks->isNotEmpty() ? 'text-rose-700' : '' }}">{{ $title }} ({{ $tasks->count() }})</div>
                <ul class="divide-y divide-slate-100">
                    @forelse($tasks as $t)
                        <li class="px-5 py-3 text-sm flex items-center gap-3">
                            <form method="POST" action="{{ route('admin.crm.tareas.toggle', $t) }}">@csrf @method('PATCH')<button title="Marcar como hecho" class="w-6 h-6 rounded border border-slate-300 hover:bg-emerald-50"></button></form>
                            <div class="flex-1 min-w-0">
                                <p>{{ $t->title }}</p>
                                <p class="text-xs text-slate-400">{{ $t->due_at->isoFormat('ddd D MMM, HH:mm') }} · {{ $t->user->name }}
                                    @if($t->customer) · <a href="{{ route('admin.clientes.show', $t->customer) }}" class="text-brand-600 hover:underline">{{ $t->customer->name }}</a>@endif</p>
                            </div>
                            @if($t->customer?->phone)<a href="{{ $t->customer->whatsappLink('¡Hola '.$t->customer->firstName().'!') }}" target="_blank" class="text-xs font-semibold text-wa-500 hover:underline">WhatsApp</a>@endif
                        </li>
                    @empty<li class="px-5 py-4 text-sm text-slate-400">Nada {{ $title === 'Hoy' ? 'para hoy' : 'aquí' }} 🎉</li>@endforelse
                </ul>
            </div>
        @endforeach
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200">
            <div class="px-5 py-3 border-b border-slate-100 font-semibold">🏆 Mejores clientes</div>
            <ul class="divide-y divide-slate-100">
                @forelse($top as $c)
                    <li class="px-5 py-2.5 text-sm flex justify-between gap-2"><a href="{{ route('admin.clientes.show', $c) }}" class="hover:underline truncate">{{ $c->name }}</a><span class="font-semibold whitespace-nowrap">{{ money($c->spent) }}</span></li>
                @empty<li class="px-5 py-4 text-sm text-slate-400">Aún no hay ventas con cliente.</li>@endforelse
            </ul>
        </div>
        <div class="bg-white rounded-xl border border-slate-200">
            <div class="px-5 py-3 border-b border-slate-100 font-semibold">📝 Últimas notas del equipo</div>
            <ul class="divide-y divide-slate-100">
                @forelse($recent as $i)
                    <li class="px-5 py-2.5 text-sm"><p class="text-xs text-slate-400">{{ $i->typeLabel() }} · {{ $i->created_at->diffForHumans() }} · {{ $i->user?->name }}</p>
                        <p><a href="{{ route('admin.clientes.show', $i->customer) }}" class="font-medium hover:underline">{{ $i->customer->name }}</a>: {{ \Illuminate\Support\Str::limit($i->body, 90) }}</p></li>
                @empty<li class="px-5 py-4 text-sm text-slate-400">Sin notas todavía.</li>@endforelse
            </ul>
        </div>
        <div class="bg-sky-50 border border-sky-200 text-sky-900 text-sm rounded-xl p-4">💡 También puedes decirle al <a href="{{ route('admin.asistente') }}" class="font-semibold underline">asistente 🎙️</a>: «recuérdame llamar a Carlos el viernes», «nota para Carlos: quiere talla 41» o «¿qué seguimientos tengo hoy?».</div>
    </div>
</div>
@endsection
