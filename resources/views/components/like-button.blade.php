@props(['type', 'id', 'liked' => false, 'count' => 0, 'size' => 'md'])
@php $small = $size === 'sm'; @endphp
<button type="button"
        x-data="{ liked: @js((bool) $liked), count: @js((int) $count), busy: false,
                  async go() { if (this.busy) return; this.busy = true; const r = await RyE.toggleLike(@js($type), @js($id)); this.liked = r.liked; this.count = r.count; this.busy = false; } }"
        @click.prevent="go()"
        {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 group select-none '.($small ? 'text-xs' : 'text-sm')]) }}
        :aria-pressed="liked" title="Me gusta">
    <span class="relative inline-flex">
        <svg :class="liked ? 'text-rose-500 scale-110' : 'text-brand-500 group-hover:text-rose-400'" class="{{ $small ? 'w-4 h-4' : 'w-6 h-6' }} transition-transform duration-200" viewBox="0 0 24 24" :fill="liked ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
    </span>
    <span class="font-medium tabular-nums" :class="liked ? 'text-rose-600' : 'text-brand-700'" x-text="count"></span>
</button>
