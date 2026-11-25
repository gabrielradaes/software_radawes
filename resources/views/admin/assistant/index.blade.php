@extends('admin.layouts.app')
@section('title', 'Asistente')
@section('actions')
    @can('activity.view')<a href="{{ route('admin.asistente.equipo') }}" class="text-sm text-slate-600 hover:text-slate-900 px-3 py-2 rounded-lg hover:bg-slate-200">👥 Chats del equipo</a>@endcan
    <form method="POST" action="{{ route('admin.asistente.limpiar') }}" onsubmit="return confirm('¿Empezar una conversación nueva? Lo que ya se registró en el sistema no cambia.')">
        @csrf @method('DELETE')
        <button class="text-sm text-slate-500 hover:text-slate-800 px-3 py-2 rounded-lg hover:bg-slate-200">🧹 Nueva conversación</button>
    </form>
@endsection

@section('content')
<div x-data="asistente(@js($messages), @js($pending))" class="max-w-3xl mx-auto flex flex-col bg-white rounded-2xl border border-slate-200 overflow-hidden" style="height: calc(100vh - 9.5rem); min-height: 480px">

    {{-- Conversación --}}
    <div x-ref="list" id="asistente-list" class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50">
        <template x-for="(m, i) in messages" :key="m.key">
            <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                <div :class="m.role === 'user' ? 'bg-brand-700 text-white rounded-2xl rounded-br-md' : 'bg-white border border-slate-200 text-slate-800 rounded-2xl rounded-bl-md'"
                     class="max-w-[90%] sm:max-w-[80%] px-4 py-2.5 text-[15px] leading-relaxed shadow-sm">
                    <div x-show="m.role !== 'user'" class="text-[11px] font-semibold text-brand-700 mb-0.5">🤖 Asistente</div>
                    <div x-html="m.html"></div>

                    <template x-if="m.table">
                        <div class="mt-2 overflow-x-auto">
                            <table class="text-sm w-full border-collapse">
                                <thead><tr><template x-for="h in m.table[0]"><th class="text-left text-xs uppercase text-slate-500 border-b border-slate-200 px-2 py-1" x-text="h"></th></template></tr></thead>
                                <tbody><template x-for="row in m.table[1]"><tr class="border-b border-slate-100 last:border-0"><template x-for="(c, ci) in row"><td class="px-2 py-1 align-top" :class="ci === 0 ? 'font-medium' : ''" x-text="c"></td></template></tr></template></tbody>
                            </table>
                        </div>
                    </template>

                    <template x-if="m.links && m.links.length">
                        <div class="mt-2 flex flex-wrap gap-2">
                            <template x-for="l in m.links"><a :href="l[1]" target="_blank" class="text-xs font-semibold bg-brand-50 text-brand-800 border border-brand-200 px-2.5 py-1 rounded-full hover:bg-brand-100" x-text="l[0]"></a></template>
                        </div>
                    </template>

                    <template x-if="m.confirm && i === messages.length - 1 && pending">
                        <div class="mt-3 flex gap-2">
                            <button type="button" @click="decide('confirmar')" :disabled="busy" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg disabled:opacity-50">✅ Confirmar</button>
                            <button type="button" @click="decide('cancelar')" :disabled="busy" class="bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 text-sm font-semibold px-4 py-2 rounded-lg disabled:opacity-50">✖ Cancelar</button>
                        </div>
                    </template>
                    <div class="text-[10px] mt-1 opacity-60 text-right" x-text="m.time"></div>
                </div>
            </div>
        </template>
        <div x-show="busy" class="flex justify-start"><div class="bg-white border border-slate-200 rounded-2xl px-4 py-2 text-slate-500 text-sm">Pensando…</div></div>
    </div>

    {{-- Fotos adjuntas --}}
    <div x-show="files.length" class="px-4 pt-3 flex gap-2 flex-wrap border-t border-slate-200 bg-white">
        <template x-for="(f, i) in files" :key="i">
            <div class="relative">
                <img :src="f.url" class="w-16 h-16 object-cover rounded-lg border border-slate-200">
                <button type="button" @click="removeFile(i)" class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-slate-800 text-white text-xs leading-5">×</button>
            </div>
        </template>
    </div>

    {{-- Escribir / hablar --}}
    <form @submit.prevent="send()" class="border-t border-slate-200 bg-white p-3">
        <p x-show="listening" class="text-xs text-rose-600 font-semibold mb-1.5 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-rose-600 animate-pulse"></span> Escuchando… habla claro y cerca del celular. Toca 🎤 otra vez para terminar.</p>
        <p x-show="voiceError" class="text-xs text-amber-700 mb-1.5" x-text="voiceError"></p>
        <div class="flex items-end gap-1.5 sm:gap-2">
            <button type="button" @click="toggleMic()" x-show="canVoice" :title="listening ? 'Terminar' : 'Hablar'"
                    :class="listening ? 'bg-rose-600 text-white ring-4 ring-rose-200' : 'bg-brand-700 text-white hover:bg-brand-800'"
                    class="shrink-0 w-11 h-11 sm:w-12 sm:h-12 rounded-full text-xl flex items-center justify-center transition">🎤</button>
            <label class="shrink-0 w-11 h-11 sm:w-12 sm:h-12 rounded-full bg-slate-100 hover:bg-slate-200 text-xl flex items-center justify-center cursor-pointer" title="Adjuntar fotos">
                📷<input type="file" accept="image/*" multiple class="hidden" @change="addFiles($event)">
            </label>
            <textarea x-ref="input" id="asistente-input" x-model="text" rows="1" @keydown.enter.prevent="if(!$event.shiftKey) send()" @input="grow()"
                      placeholder="Escribe o habla…" class="flex-1 min-w-0 resize-none rounded-xl border border-slate-300 px-3 py-2.5 text-[15px] max-h-40"></textarea>
            <button type="submit" :disabled="busy || (!text.trim() && !files.length)" class="shrink-0 h-11 sm:h-12 px-3 sm:px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold disabled:opacity-40" title="Enviar"><span class="hidden sm:inline">Enviar</span><span class="sm:hidden">➤</span></button>
        </div>
        <div class="flex items-center justify-between mt-2 text-xs text-slate-500">
            <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" x-model="speak" @change="saveSpeak()" class="rounded"> 🔊 Leer las respuestas en voz alta</label>
            <span x-show="!canVoice">El dictado por voz funciona en Chrome o Edge.</span>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function asistente(initial, pending) {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    return {
        seq: 0, messages: initial.map((m, i) => ({ ...m, key: "s" + (m.id ?? "w" + i) })), pending, text: '', files: [], busy: false,
        canVoice: !!SR, listening: false, voiceError: '', rec: null, base: '', speak: false,

        init() {
            try { this.speak = localStorage.getItem('rye-asistente-voz') === '1'; } catch (e) {}
            this.scroll();
        },
        add(m) { m.key = m.id ? 's' + m.id : 'l' + (++this.seq); this.messages.push(m); },
        saveSpeak() { try { localStorage.setItem('rye-asistente-voz', this.speak ? '1' : '0'); } catch (e) {} },
        scroll() { setTimeout(() => { const el = document.getElementById('asistente-list'); if (el) el.scrollTop = el.scrollHeight; }, 30); },
        grow() { const t = document.getElementById('asistente-input'); if (!t) return; t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight, 160) + 'px'; },

        // --- Voz: dictado del navegador (gratis). Se reinicia solo hasta que el usuario lo detenga.
        toggleMic() {
            if (this.listening) { this.listening = false; this.rec && this.rec.stop(); return; }
            this.voiceError = '';
            window.speechSynthesis && speechSynthesis.cancel();
            this.base = this.text ? this.text.trim() + ' ' : '';
            this.listening = true;
            this.startRec();
        },
        startRec() {
            const rec = new SR();
            rec.lang = 'es-BO';
            rec.interimResults = true;
            rec.continuous = false;
            rec.onresult = (e) => {
                let finals = '', interim = '';
                for (const r of e.results) { r.isFinal ? finals += r[0].transcript : interim += r[0].transcript; }
                if (finals) { this.base = (this.base + finals.trim() + ' '); }
                this.text = (this.base + interim).trimStart();
                this.grow();
            };
            rec.onerror = (e) => {
                if (e.error === 'not-allowed' || e.error === 'service-not-allowed') { this.voiceError = 'Permite el micrófono para esta página (candado 🔒 junto a la dirección).'; this.listening = false; }
                else if (e.error === 'network') { this.voiceError = 'Sin conexión: el dictado necesita internet.'; this.listening = false; }
            };
            rec.onend = () => { if (this.listening) { try { rec.start(); } catch (e) { this.listening = false; } } };
            this.rec = rec;
            try { rec.start(); } catch (e) { this.voiceError = 'No se pudo iniciar el micrófono.'; this.listening = false; }
        },

        addFiles(ev) {
            for (const f of ev.target.files) { this.files.push({ file: f, url: URL.createObjectURL(f) }); }
            ev.target.value = '';
        },
        removeFile(i) { URL.revokeObjectURL(this.files[i].url); this.files.splice(i, 1); },

        async post(url, body) {
            this.busy = true; this.scroll();
            try {
                const r = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body });
                const data = await r.json().catch(() => ({}));
                if (!r.ok) {
                    const msg = data.errors ? Object.values(data.errors).flat().join(' ') : (r.status === 419 ? 'La sesión expiró: recarga la página.' : 'Ocurrió un error. Intenta de nuevo.');
                    this.add({ role: 'assistant', html: msg, time: '' });
                    return;
                }
                this.add(data);
                this.pending = !!data.confirm;
                this.say(data);
            } catch (e) {
                this.add({ role: 'assistant', html: 'Sin conexión con el servidor. Revisa tu internet.', time: '' });
            } finally { this.busy = false; this.scroll(); }
        },

        async send() {
            if (this.busy) return;
            if (this.listening) { this.listening = false; this.rec && this.rec.stop(); }
            const text = this.text.trim();
            if (!text && !this.files.length) return;
            const fd = new FormData();
            fd.append('text', text);
            this.files.forEach(f => fd.append('images[]', f.file));
            const shown = text || ('📷 ' + this.files.length + ' foto(s)');
            this.add({ role: 'user', html: shown.replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])) + (text && this.files.length ? ' <span class="opacity-75">📷 ' + this.files.length + '</span>' : ''), time: '' });
            this.text = ''; this.base = ''; this.files = []; this.grow();
            await this.post(@js(route('admin.asistente.mensaje')), fd);
        },

        async decide(action) {
            this.add({ role: 'user', html: action === 'confirmar' ? 'Sí, confirmo' : 'Cancelar', time: '' });
            this.pending = false;
            await this.post(action === 'confirmar' ? @js(route('admin.asistente.confirmar')) : @js(route('admin.asistente.cancelar')), new FormData());
        },

        // --- Leer la respuesta en voz alta (gratis, voz del dispositivo)
        say(m) {
            if (!this.speak || !window.speechSynthesis) return;
            const plain = (m.text || '').replace(/\*\*|\*/g, '').replace(/[•✅🤖📷🎤🔊😕🤔]/gu, '').replace(/\(.*?\)/g, '');
            const u = new SpeechSynthesisUtterance(plain.split('\n\n')[0]);
            u.lang = 'es-ES';
            const v = speechSynthesis.getVoices().find(v => v.lang && v.lang.startsWith('es'));
            if (v) u.voice = v;
            speechSynthesis.cancel(); speechSynthesis.speak(u);
        },
    };
}
</script>
@endpush
