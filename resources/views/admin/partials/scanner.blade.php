{{-- Escáner: lector USB/Bluetooth (escribe + Enter) o cámara del celular. Emite el evento "scanned" con {code}. --}}
<div x-data="barcodeScanner()" class="flex gap-2 items-center">
    <input type="text" autocomplete="off" placeholder="{{ $placeholder ?? 'Escanea o escribe el código de la etiqueta…' }}"
           @keydown.enter.prevent="emit($event.target.value); $event.target.value = ''"
           class="flex-1 min-w-0 rounded-lg border-2 border-dashed border-brand-300 bg-brand-50/40 px-3 py-2.5 text-sm font-mono focus:border-brand-600 focus:bg-white">
    <button type="button" @click="camera()" class="shrink-0 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold px-3 py-2.5 rounded-lg" title="Escanear con la cámara">📷 Cámara</button>

    <div x-show="open" x-cloak class="fixed inset-0 z-50 bg-black/85 flex flex-col items-center justify-center p-4 gap-3" @keydown.escape.window="stop()">
        <p class="text-white text-sm">Apunta al código de barras de la etiqueta</p>
        <div class="relative w-full max-w-md">
            <video x-ref="video" playsinline muted class="w-full rounded-xl bg-black"></video>
            <div class="absolute inset-x-6 top-1/2 h-0.5 bg-rose-500/80"></div>
        </div>
        <p x-show="error" x-text="error" class="text-amber-300 text-sm text-center max-w-md"></p>
        <button type="button" @click="stop()" class="bg-white text-slate-800 font-semibold px-5 py-2 rounded-lg">Cerrar</button>
    </div>
</div>

@once
@push('scripts')
<script>
function barcodeScanner() {
    return {
        open: false, stream: null, error: '', zx: null,
        emit(code) {
            code = (code || '').trim();
            if (!code) return;
            this.$dispatch('scanned', { code });
        },
        async camera() {
            this.error = '';
            if (!navigator.mediaDevices?.getUserMedia) { this.error = 'Este navegador no permite usar la cámara.'; this.open = true; return; }
            this.open = true;
            const video = this.$refs.video;
            try {
                if ('BarcodeDetector' in window) {
                    const det = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'qr_code'] });
                    this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                    video.srcObject = this.stream;
                    await video.play();
                    const loop = async () => {
                        if (!this.open) return;
                        try { const r = await det.detect(video); if (r.length) return this.found(r[0].rawValue); } catch (e) {}
                        requestAnimationFrame(loop);
                    };
                    loop();
                } else {
                    // iPhone / Windows: lector ZXing (gratis, se carga solo cuando hace falta)
                    if (!window.ZXing) await new Promise((ok, fail) => { const s = document.createElement('script'); s.src = 'https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js'; s.onload = ok; s.onerror = fail; document.head.appendChild(s); });
                    this.zx = new ZXing.BrowserMultiFormatReader();
                    await this.zx.decodeFromConstraints({ video: { facingMode: 'environment' } }, video, (res) => { if (res) this.found(res.getText()); });
                }
            } catch (e) {
                this.error = 'No se pudo abrir la cámara. Revisa el permiso (candado 🔒 junto a la dirección).';
            }
        },
        found(code) {
            navigator.vibrate && navigator.vibrate(80);
            this.stop();
            this.emit(code);
        },
        stop() {
            this.open = false;
            this.stream && this.stream.getTracks().forEach(t => t.stop());
            this.stream = null;
            if (this.zx) { this.zx.reset(); this.zx = null; }
        },
    };
}
</script>
@endpush
@endonce
