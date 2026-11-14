@extends('admin.layouts.app')
@section('title', 'Leads de WhatsApp')

@section('content')
@php $colors = ['nuevo' => 'bg-emerald-100 text-emerald-800', 'contactado' => 'bg-sky-100 text-sky-800', 'vendido' => 'bg-brand-100 text-brand-800', 'perdido' => 'bg-slate-200 text-slate-600']; @endphp
<div class="flex flex-wrap gap-2 mb-5 text-sm">
    <a href="{{ route('admin.leads.index') }}" class="px-3 py-1.5 rounded-full border {{ !$status ? 'bg-brand-900 text-white border-brand-900' : 'bg-white border-slate-200' }}">Todos ({{ $counts->sum() }})</a>
    @foreach(['nuevo', 'contactado', 'vendido', 'perdido'] as $s)
        <a href="{{ route('admin.leads.index', ['estado' => $s]) }}" class="px-3 py-1.5 rounded-full border capitalize {{ $status === $s ? 'bg-brand-900 text-white border-brand-900' : 'bg-white border-slate-200' }}">{{ $s }} ({{ $counts[$s] ?? 0 }})</a>
    @endforeach
</div>
<p class="text-xs text-slate-500 mb-4">Cada fila es un clic en "Pedir por WhatsApp". El cliente escribe desde su propio celular: busca el mensaje en el WhatsApp de la sucursal y actualiza aquí el estado.</p>
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 text-left"><tr><th class="px-4 py-3">Cuándo</th><th class="px-4 py-3">Interés</th><th class="px-4 py-3">Origen</th><th class="px-4 py-3">Sucursal</th><th class="px-4 py-3">Estado</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($leads as $l)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ $l->created_at->isoFormat('D MMM HH:mm') }}<span class="block text-xs text-slate-400">{{ $l->created_at->diffForHumans() }}</span></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            @if($l->product?->primaryImage)<img src="{{ $l->product->primaryImage->url() }}" class="w-10 h-10 rounded object-cover">@endif
                            <div>
                                <p class="font-medium">{{ $l->product?->name ?? ($l->post?->title ?? 'Consulta general') }}</p>
                                @if($l->variant)<p class="text-xs text-slate-500">{{ $l->variant->label() }} · {{ $l->variant->sku }}</p>@endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ $l->source }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $l->branch->name }}</td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('admin.leads.update', $l) }}">@csrf @method('PATCH')
                            <select name="status" onchange="this.form.submit()" class="text-xs rounded-full border-0 px-3 py-1 capitalize font-medium {{ $colors[$l->status] }}">
                                @foreach(['nuevo', 'contactado', 'vendido', 'perdido'] as $s)<option value="{{ $s }}" @selected($l->status === $s)>{{ $s }}</option>@endforeach
                            </select>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Sin leads.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $leads->links() }}</div>
@endsection
