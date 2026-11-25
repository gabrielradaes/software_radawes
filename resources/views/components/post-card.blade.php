@props(['post', 'full' => false])
@php
    $liked = $post->liked ?? false;
    $cs = $post->colorStyle();                       // null = publicación blanca normal
    $text = trim(($post->title ? $post->title."\n" : '').($post->body ?? ''));
    // Solo texto y con color: se muestra en grande sobre el color (como los estados de colores)
    $big = $cs && ! $post->media->count() && $text !== '' && mb_strlen($text) <= 220;
    $muted = $cs ? 'opacity-75' : 'text-brand-500';
@endphp
@once
    <style>
        .post-colored .pc-actions .text-brand-500, .post-colored .pc-actions .text-brand-700 { color: inherit; }
        .post-colored .pc-actions a, .post-colored .pc-actions button { color: inherit; }
    </style>
@endonce
<article class="rounded-2xl overflow-hidden shadow-sm {{ $cs ? 'post-colored' : 'bg-white border border-brand-100' }}"
         @if($cs) style="background: {{ $cs['bg'] }}; color: {{ $cs['fg'] }};" @endif>
    {{-- Cabecera --}}
    <header class="flex items-center gap-3 px-4 py-3">
        <span class="w-10 h-10 rounded-full bg-brand-900 text-gold-400 font-display font-bold text-sm flex items-center justify-center ring-2 ring-gold-500/40">RW</span>
        <div class="flex-1 min-w-0">
            <p class="font-semibold text-sm leading-tight">{{ $post->branch?->name ?? 'RyE' }}</p>
            <p class="text-xs {{ $muted }}">{{ $post->published_at->diffForHumans() }}
                @if($post->is_pinned) · <span class="{{ $cs ? '' : 'text-gold-600' }} font-semibold">📌 Fijado</span> @endif
            </p>
        </div>
        @if($post->type === 'oferta')
            <span class="text-[11px] font-bold uppercase tracking-wider bg-rose-600 text-white px-2 py-1 rounded-full">Oferta</span>
        @endif
    </header>

    {{-- Solo texto sobre color, en grande --}}
    @if($big)
        <a href="{{ route('feed.show', $post) }}" class="block px-6 py-14 min-h-[260px] flex flex-col items-center justify-center text-center">
            @if($post->title)<h2 class="font-display font-extrabold text-2xl sm:text-3xl leading-tight">{{ $post->title }}</h2>@endif
            @if($post->body)<p class="mt-3 text-lg sm:text-xl font-semibold leading-snug whitespace-pre-line">{!! preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($post->body)) !!}</p>@endif
        </a>
    @endif

    {{-- Media (carrusel simple) --}}
    @if($post->media->count())
        @php
            // TikTok y Facebook se ven verticales (video + texto): cuadro alto en vez de cuadrado
            $social = $post->media->contains(fn ($m) => $m->type === 'video' && ! $m->isLocalVideo() && preg_match('~tiktok\.com|facebook\.com|fb\.watch~i', $m->path));
        @endphp
        <div x-data="{ i: 0, n: {{ $post->media->count() }} }" class="relative {{ $social ? 'h-[min(78vh,740px)]' : 'aspect-square' }} bg-brand-100 overflow-hidden">
            @foreach($post->media as $k => $m)
                @if($m->isLocalVideo())
                    <div x-show="i === {{ $k }}" x-cloak="{{ $k ? 'true' : '' }}" class="absolute inset-0 bg-black">
                        <video src="{{ $m->url() }}" @if($m->posterUrl()) poster="{{ $m->posterUrl() }}" @endif controls playsinline preload="metadata" class="w-full h-full object-contain"></video>
                    </div>
                @elseif($m->type === 'video' && ($embed = video_embed_url($m->path)))
                    <div x-show="i === {{ $k }}" x-cloak="{{ $k ? 'true' : '' }}" class="absolute inset-0 bg-white">
                        <iframe src="{{ $embed }}" class="w-full h-full" style="border:0" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>
                    </div>
                @elseif($m->type !== 'video')
                    <a href="{{ route('feed.show', $post) }}" x-show="i === {{ $k }}" x-cloak="{{ $k ? 'true' : '' }}" class="absolute inset-0">
                        <img src="{{ $m->url() }}" alt="{{ $post->title }}" loading="lazy" class="w-full h-full object-cover">
                    </a>
                @endif
            @endforeach
            @if($post->media->count() > 1)
                <button @click="i = (i - 1 + n) % n" class="absolute left-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/80 hover:bg-white text-brand-900 flex items-center justify-center shadow">‹</button>
                <button @click="i = (i + 1) % n" class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/80 hover:bg-white text-brand-900 flex items-center justify-center shadow">›</button>
                <div class="absolute bottom-3 inset-x-0 flex justify-center gap-1.5 pointer-events-none">
                    @foreach($post->media as $k => $m)
                        <span :class="i === {{ $k }} ? 'bg-white' : 'bg-white/50'" class="w-2 h-2 rounded-full"></span>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Acciones --}}
    <div class="pc-actions px-4 pt-3 flex items-center gap-5">
        <x-like-button type="post" :id="$post->id" :liked="$liked" :count="$post->likes_count ?? 0"/>
        <a href="{{ route('feed.show', $post) }}#comentarios-post-{{ $post->id }}" class="inline-flex items-center gap-1.5 text-sm text-brand-700 hover:text-brand-900">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            <span class="font-medium tabular-nums">{{ $post->comments_count ?? $post->comments->count() }}</span>
        </a>
        <button type="button" class="inline-flex items-center gap-1.5 text-sm text-brand-700 hover:text-brand-900" title="Compartir"
                x-data @click="navigator.share ? navigator.share({title: @js($post->title), url: @js(route('feed.show', $post))}) : navigator.clipboard.writeText(@js(route('feed.show', $post))).then(() => alert('Enlace copiado'))">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
        </button>
        <a href="{{ route('whatsapp', ['post' => $post->id, 'producto' => $post->product?->slug, 'origen' => 'feed']) }}" target="_blank" rel="noopener" class="ml-auto inline-flex items-center gap-1.5 bg-wa-500 hover:bg-wa-600 !text-white text-xs font-semibold px-3 py-1.5 rounded-full">
            <x-icon-whatsapp class="w-3.5 h-3.5"/> Consultar
        </a>
    </div>

    {{-- Texto --}}
    <div class="px-4 pt-3 pb-4">
        @unless($big)
            @if($post->title)
                <h2 class="font-display font-bold text-lg leading-snug"><a href="{{ route('feed.show', $post) }}" class="{{ $cs ? 'hover:underline' : 'hover:text-brand-500' }}">{{ $post->title }}</a></h2>
            @endif
            @if($post->body)
                <div x-data="{ more: {{ $full ? 'true' : 'false' }} }" class="mt-1 text-sm {{ $cs ? '' : 'text-brand-800' }}">
                    <p class="prose-body" :class="more ? '' : 'line-clamp-3'">{!! preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($post->body)) !!}</p>
                    @if(! $full && mb_strlen($post->body) > 160)
                        <button @click="more = !more" class="{{ $cs ? 'underline' : 'text-brand-500' }} font-medium text-xs mt-1" x-text="more ? 'Ver menos' : 'Ver más'"></button>
                    @endif
                </div>
            @endif
        @endunless

        {{-- Producto etiquetado (siempre en tarjeta clara para que se lea bien) --}}
        @if($post->product)
            <a href="{{ route('products.show', $post->product) }}" class="mt-3 flex items-center gap-3 rounded-xl border border-brand-100 bg-brand-50 hover:bg-brand-100 text-brand-900 p-2.5 transition">
                @if($post->product->primaryImage)
                    <img src="{{ $post->product->primaryImage->url() }}" alt="" class="w-14 h-14 rounded-lg object-cover">
                @endif
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] uppercase tracking-wider text-brand-500">Producto</p>
                    <p class="font-semibold text-sm truncate">{{ $post->product->name }}</p>
                    <p class="text-sm font-bold text-brand-900">{{ money($post->product->base_price) }}
                        @if($post->product->hasDiscount())<span class="text-xs text-brand-500 line-through font-normal ml-1">{{ money($post->product->compare_at_price) }}</span>@endif
                    </p>
                </div>
                <span class="text-brand-500">›</span>
            </a>
        @endif
    </div>

    {{-- Comentarios --}}
    @if($full)
        <div class="border-t border-brand-100 px-4 py-4 bg-white text-brand-900">
            <x-comments :model="$post" type="post" compact/>
        </div>
    @elseif($post->relationLoaded('approvedComments') && $post->approvedComments->count())
        <div class="px-4 py-3 space-y-1.5 border-t {{ $cs ? '' : 'border-brand-100' }}" @if($cs) style="border-color: {{ $cs['soft'] }}" @endif>
            @foreach($post->approvedComments->take(2) as $c)
                <p class="text-sm"><span class="font-semibold">{{ $c->author_name }}</span> <span class="{{ $cs ? 'opacity-90' : 'text-brand-800' }}">{{ \Illuminate\Support\Str::limit($c->body, 90) }}</span></p>
            @endforeach
            @if(($post->comments_count ?? 0) > 2)
                <a href="{{ route('feed.show', $post) }}" class="text-xs {{ $muted }} hover:underline">Ver los {{ $post->comments_count }} comentarios</a>
            @endif
        </div>
    @endif
</article>
