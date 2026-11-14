@props(['product'])
<article class="group bg-white rounded-2xl overflow-hidden border border-brand-100 hover:shadow-xl hover:-translate-y-0.5 transition duration-300">
    <a href="{{ route('products.show', $product) }}" class="block relative aspect-square overflow-hidden bg-brand-100">
        @if($product->primaryImage)
            <img src="{{ $product->primaryImage->url() }}" alt="{{ $product->primaryImage->alt }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
        @endif
        <div class="absolute top-3 left-3 flex flex-col gap-1.5">
            @if($product->hasDiscount())
                <span class="bg-rose-600 text-white text-[11px] font-bold px-2 py-0.5 rounded-full">-{{ $product->discountPercent() }}%</span>
            @endif
            @if($product->published_at && $product->published_at->gt(now()->subDays(7)))
                <span class="bg-gold-500 text-brand-950 text-[11px] font-bold px-2 py-0.5 rounded-full">Nuevo</span>
            @endif
        </div>
        <div class="absolute bottom-3 right-3 bg-white/90 backdrop-blur rounded-full px-2.5 py-1 shadow">
            <x-like-button type="product" :id="$product->id" :count="$product->likes_count ?? 0" size="sm"/>
        </div>
    </a>
    <div class="p-4">
        <p class="text-[11px] uppercase tracking-wider text-brand-500">{{ $product->category->name }}</p>
        <h3 class="font-semibold text-brand-900 leading-snug mt-0.5">
            <a href="{{ route('products.show', $product) }}" class="hover:text-brand-500">{{ $product->name }}</a>
        </h3>
        <div class="mt-2 flex items-end justify-between gap-2">
            <div>
                <span class="text-lg font-bold text-brand-900">{{ money($product->base_price, $product->currency) }}</span>
                @if($product->hasDiscount())
                    <span class="ml-1 text-xs text-brand-500 line-through">{{ money($product->compare_at_price, $product->currency) }}</span>
                @endif
            </div>
            <a href="{{ route('whatsapp', ['producto' => $product->slug, 'origen' => 'tarjeta']) }}" target="_blank" rel="noopener"
               class="shrink-0 inline-flex items-center gap-1.5 bg-wa-500 hover:bg-wa-600 text-white text-xs font-semibold px-3 py-1.5 rounded-full transition" title="Pedir por WhatsApp">
                <x-icon-whatsapp class="w-3.5 h-3.5"/> Pedir
            </a>
        </div>
    </div>
</article>
