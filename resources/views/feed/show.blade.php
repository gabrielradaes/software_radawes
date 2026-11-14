@extends('layouts.app')
@section('title', ($post->title ?? 'Publicación').' · '.$currentBranch->name)
@section('meta_description', \Illuminate\Support\Str::limit($post->body, 150))

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">
    <a href="{{ route('feed.index') }}" class="text-sm text-brand-500 hover:text-brand-900">← Volver a novedades</a>
    <div class="mt-4">
        <x-post-card :post="$post" full/>
    </div>
</div>
@endsection
