{{--
    Fotos y videos con editor. Parámetros:
    - images: nombre del campo de fotos (media[] / images[])
    - videos: true para permitir videos (publicaciones)
--}}
@php $maxVideoMb = \App\Support\VideoUpload::maxMb(); $videos = $videos ?? false; @endphp
<div x-data="ryeMediaUploader({ images: @js($images ?? 'media[]'), videos: @js($videos), maxVideoMb: {{ $maxVideoMb }} })"
     @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)" class="space-y-3">
    <input type="file" x-ref="imageIn" accept="image/*" multiple class="hidden" @change="add($event.target.files); $event.target.value = ''">
    @if($videos)<input type="file" x-ref="videoIn" accept="video/*" multiple class="hidden" @change="add($event.target.files); $event.target.value = ''">@endif

    <div :class="dragging ? 'border-brand-500 bg-brand-50' : 'border-slate-300'" class="border-2 border-dashed rounded-xl p-4 text-center transition">
        <div class="flex flex-wrap justify-center gap-2">
            <button type="button" @click="pick('image')" class="bg-brand-900 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg">📷 Agregar fotos</button>
            @if($videos)
                <button type="button" @click="pick('video')" class="bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg">🎬 Agregar video</button>
                <button type="button" x-show="canEditVideo" @click="openVideoEditor([])" class="bg-gradient-to-r from-fuchsia-600 to-orange-500 text-white text-sm font-semibold px-4 py-2.5 rounded-lg">✨ Crear video con el editor</button>
            @endif
        </div>
        <p class="text-xs text-slate-500 mt-2">O arrastra los archivos aquí. Después toca <strong>✏️ Editar</strong> para ponerle fondo blanco, recortar, textos{{ $videos ? ', o editar el video como en CapCut' : '' }}.</p>
    </div>

    <template x-if="errors.length">
        <div class="bg-rose-50 border border-rose-200 text-rose-800 text-sm rounded-lg px-4 py-3">
            <p class="font-semibold mb-1">Revisa esto:</p>
            <ul class="list-disc ml-5 space-y-0.5"><template x-for="e in errors"><li x-text="e"></li></template></ul>
        </div>
    </template>

    <div x-show="items.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
        <template x-for="(it, i) in items" :key="it.id">
            <div class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                <div class="relative aspect-square bg-slate-900">
                    <template x-if="it.kind === 'image'"><img :src="it.url" class="w-full h-full object-contain bg-slate-100"></template>
                    <template x-if="it.kind === 'video' && it.posterUrl"><img :src="it.posterUrl" class="w-full h-full object-contain"></template>
                    <template x-if="it.kind === 'video' && !it.posterUrl"><div class="w-full h-full flex items-center justify-center text-white text-3xl" x-text="it.checking ? '⏳' : '🎬'"></div></template>
                    <span x-show="it.kind === 'video'" class="absolute top-1.5 left-1.5 bg-black/70 text-white text-[10px] font-semibold px-1.5 py-0.5 rounded">▶ VIDEO</span>
                    <span x-show="it.edited" class="absolute top-1.5 right-1.5 bg-emerald-600 text-white text-[10px] font-semibold px-1.5 py-0.5 rounded">✔ Editada</span>
                    <span class="absolute bottom-1.5 left-1.5 bg-black/60 text-white text-[10px] px-1.5 py-0.5 rounded" x-text="i + 1"></span>
                </div>
                <p x-show="it.warning" class="text-[11px] text-amber-800 bg-amber-50 px-2 py-1" x-text="it.warning"></p>
                <div class="flex items-center gap-1 p-1.5">
                    <button type="button" @click="edit(it)" x-show="it.kind === 'image' || canEditVideo" class="flex-1 text-xs font-semibold bg-brand-50 hover:bg-brand-100 text-brand-900 rounded-lg py-2">✏️ Editar</button>
                    <button type="button" @click="move(it, -1)" :disabled="i === 0" class="text-xs px-2 py-2 rounded-lg hover:bg-slate-100 disabled:opacity-30" title="Mover antes">←</button>
                    <button type="button" @click="move(it, 1)" :disabled="i === items.length - 1" class="text-xs px-2 py-2 rounded-lg hover:bg-slate-100 disabled:opacity-30" title="Mover después">→</button>
                    <button type="button" @click="remove(it)" class="text-xs px-2 py-2 rounded-lg hover:bg-rose-50 text-rose-600" title="Quitar">🗑</button>
                </div>
            </div>
        </template>
    </div>

    {{-- Subiendo… --}}
    <div x-show="uploading" x-cloak class="fixed inset-0 z-50 bg-slate-900/70 flex items-center justify-center p-6">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm text-center shadow-xl">
            <p class="font-semibold text-lg">Subiendo… <span x-text="progress + '%'"></span></p>
            <div class="mt-3 h-3 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-emerald-500 transition-all" :style="'width:' + progress + '%'"></div></div>
            <p class="text-xs text-slate-500 mt-3" x-text="progress >= 100 ? 'Guardando en la página…' : 'No cierres esta página.'"></p>
        </div>
    </div>
</div>

@once
    @push('scripts')
        @foreach(['photo-editor', 'video-editor', 'media-uploader'] as $js)
            <script src="{{ asset('js/editor/'.$js.'.js') }}?v={{ @filemtime(public_path('js/editor/'.$js.'.js')) }}"></script>
        @endforeach
    @endpush
@endonce
