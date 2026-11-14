{{-- Estilos compilados + colores de la tienda como variables CSS + Alpine local (sin CDN) --}}
@php
    $cssFile = public_path('css/app.css');
    $cssVer = file_exists($cssFile) ? filemtime($cssFile) : time();
@endphp
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ $cssVer }}">
<style>:root { {!! \App\Support\Theme::cssVars($palette ?? \App\Support\Theme::defaultPalette()) !!} }</style>
@if($alpine ?? true)
    @if($collapse ?? false)<script defer src="{{ asset('js/alpine-collapse.min.js') }}"></script>@endif
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>
@endif
