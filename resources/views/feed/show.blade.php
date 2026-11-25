@extends('layouts.app')
@section('title', ($post->title ?? 'Publicación').' · '.$currentBranch->name)
{{-- Siempre texto (nunca null): con null, @section abre un bloque que no se cierra --}}
@section('meta_description', \Illuminate\Support\Str::limit((string) ($post->body ?: $post->title ?: $currentBranch->name), 150))
@if($img = $post->media->map->previewUrl()->filter()->first())
    @section('og_image', $img)
@endif

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">
    <a href="{{ route('feed.index') }}" class="text-sm text-brand-500 hover:text-brand-900">← Volver a novedades</a>
    <div class="mt-4">
        <x-post-card :post="$post" full/>
    </div>
</div>
@endsection
