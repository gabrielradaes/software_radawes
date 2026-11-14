@extends('layouts.app')
@section('title', 'Novedades · '.$branch->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 py-10">
    <div class="grid lg:grid-cols-[1fr_320px] gap-10">
        <div class="max-w-2xl w-full mx-auto lg:mx-0">
            <div class="mb-6">
                <p class="text-xs uppercase tracking-[0.2em] text-brand-500 font-semibold">Comunidad {{ $branch->name }}</p>
                <h1 class="font-display text-3xl md:text-4xl font-bold mt-1">Novedades</h1>
                <p class="text-brand-700 mt-1">Lanzamientos, ofertas y lo que pasa en la tienda. Dale ❤️ y comenta.</p>
            </div>
            <div class="space-y-6">
                @forelse($posts as $post)
                    <x-post-card :post="$post"/>
                @empty
                    <p class="text-brand-500">Aún no hay publicaciones.</p>
                @endforelse
            </div>
            <div class="mt-10">{{ $posts->links() }}</div>
        </div>

        <aside class="space-y-6 lg:sticky lg:top-24 self-start">
            <div class="bg-white rounded-2xl border border-brand-100 p-5">
                <div class="flex items-center gap-3">
                    <span class="w-12 h-12 rounded-full bg-brand-900 text-gold-400 font-display font-bold flex items-center justify-center ring-2 ring-gold-500/40">RW</span>
                    <div>
                        <p class="font-semibold">{{ $branch->name }}</p>
                        <p class="text-xs text-brand-500">{{ $branch->tagline }}</p>
                    </div>
                </div>
                <div class="mt-4 space-y-2">
                    @foreach($branch->socialLinks as $link)
                        <a href="{{ $link->url }}" target="_blank" rel="noopener" class="flex items-center gap-3 text-sm px-3 py-2 rounded-xl hover:bg-brand-50">
                            <x-icon-social :platform="$link->platform" class="w-5 h-5 text-brand-700"/>
                            <span class="font-medium">{{ $link->handle }}</span>
                            <span class="ml-auto text-xs text-brand-500 capitalize">{{ $link->platform }}</span>
                        </a>
                    @endforeach
                </div>
                <a href="{{ route('whatsapp', ['origen' => 'feed-lateral']) }}" target="_blank" rel="noopener" class="mt-4 w-full inline-flex items-center justify-center gap-2 bg-wa-500 hover:bg-wa-600 text-white text-sm font-semibold px-4 py-2.5 rounded-full">
                    <x-icon-whatsapp class="w-4 h-4"/> Escríbenos
                </a>
            </div>
            <div class="bg-brand-900 text-white rounded-2xl p-5">
                <p class="font-display text-lg font-bold">📍 {{ $branch->city }}</p>
                <p class="text-sm text-brand-200 mt-1">{{ $branch->address }}</p>
                @if($branch->opening_hours)<p class="text-sm text-brand-200 mt-1">{{ $branch->opening_hours }}</p>@endif
            </div>
        </aside>
    </div>
</div>
@endsection
