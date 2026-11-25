/*
 * Editor de video de RyE, estilo CapCut, todo en el navegador (no sube nada hasta exportar).
 * Clips de video y fotos · recortar inicio/fin · dividir · duplicar · ordenar · velocidad · volumen
 * Formato 9:16 / 1:1 / 4:5 / 16:9 · llenar o ajustar con fondo desenfocado o de color
 * Filtros · textos con tiempo de aparición y animación · música · transición suave
 * Exporta a MP4 (o WebM si el navegador no graba MP4) + portada.
 *
 * Uso: RyEVideoEditor.open([archivos], { maxMb, onDone(videoBlob, portadaBlob, nombre) })
 * Requiere photo-editor.js (RyEEditorKit) cargado antes.
 */
(function () {
    'use strict';
    if (window.RyEVideoEditor) return;
    const K = () => window.RyEEditorKit;

    const FORMATS = { '9:16': [720, 1280], '1:1': [1080, 1080], '4:5': [864, 1080], '16:9': [1280, 720] };
    const FILTERS = [
        ['Original', { b: 100, c: 100, s: 100, g: 0 }],
        ['Vivo', { b: 102, c: 112, s: 135, g: 0 }],
        ['Brillante', { b: 115, c: 105, s: 105, g: 0 }],
        ['Cálido', { b: 103, c: 102, s: 115, g: 0, sepia: 18 }],
        ['Cine', { b: 95, c: 125, s: 85, g: 0 }],
        ['Blanco y negro', { b: 100, c: 110, s: 100, g: 100 }],
    ];
    const BG_SWATCHES = ['#000000', '#ffffff', '#111827', '#78350f', '#b91c1c', '#1d4ed8', '#6d28d9', '#db2777', '#15803d', '#facc15'];
    const SPEEDS = [0.5, 1, 1.5, 2, 3];
    const canFilter = (() => { try { const c = document.createElement('canvas').getContext('2d'); c.filter = 'blur(2px)'; return c.filter === 'blur(2px)'; } catch (e) { return false; } })();

    const fmtT = (s) => { s = Math.max(0, s); const m = Math.floor(s / 60); const r = s - m * 60; return m + ':' + (r < 10 ? '0' : '') + r.toFixed(1); };
    let uid = 0;

    function injectCss() {
        K().injectCss();
        if (document.getElementById('rye-ve-css')) return;
        const css = `
        .rye-ve-main{flex:1;min-height:0;display:flex;flex-direction:column}
        .rye-ve-stage{flex:1;min-height:0;display:flex;align-items:center;justify-content:center;padding:10px;position:relative;overflow:hidden}
        .rye-ve-wrap{position:relative;flex:none;background:#000;box-shadow:0 10px 40px rgba(0,0,0,.5)}
        .rye-ve-wrap canvas{display:block;width:100%;height:100%}
        .rye-ve-bar{display:flex;align-items:center;gap:10px;padding:6px 12px}
        .rye-ve-bar .t{font-variant-numeric:tabular-nums;color:#94a3b8;min-width:110px}
        .rye-ve-tl{position:relative;overflow-x:auto;overflow-y:hidden;padding:6px 12px 10px;background:#020617;border-top:1px solid #1e293b;cursor:pointer}
        .rye-ve-track{position:relative;display:flex;gap:3px;height:58px;min-width:100%}
        .rye-ve-clip{position:relative;flex:none;height:58px;border-radius:8px;background:#1e293b center/cover no-repeat;border:2px solid transparent;overflow:hidden;cursor:pointer}
        .rye-ve-clip.sel{border-color:#facc15}
        .rye-ve-clip span{position:absolute;left:4px;bottom:3px;font-size:11px;background:rgba(0,0,0,.6);padding:1px 5px;border-radius:5px}
        .rye-ve-texts{position:relative;height:18px;margin-top:5px}
        .rye-ve-tbar{position:absolute;height:16px;border-radius:5px;background:#7c3aed;font-size:10px;line-height:16px;padding:0 5px;white-space:nowrap;overflow:hidden;cursor:pointer}
        .rye-ve-tbar.sel{background:#a78bfa;color:#111}
        .rye-ve-head{position:absolute;top:0;bottom:0;width:2px;background:#fff;pointer-events:none;box-shadow:0 0 0 1px #000;z-index:3}
        .rye-ve-add{flex:none;width:58px;height:58px;border-radius:8px;border:2px dashed #475569!important;background:transparent!important;font-size:22px;padding:0!important}
        .rye-ve-empty{color:#94a3b8;text-align:center;padding:30px}
        .rye-ve-prog{position:absolute;inset:0;background:rgba(2,6,23,.82);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;z-index:20;text-align:center;padding:20px}
        .rye-ve-prog .b{width:min(420px,80vw);height:12px;background:#1e293b;border-radius:99px;overflow:hidden}
        .rye-ve-prog .b i{display:block;height:100%;background:#22c55e;width:0}
        `;
        document.head.append(K().el('style', { id: 'rye-ve-css' }, css));
    }

    function pickMime() {
        if (!window.MediaRecorder) return null;
        const list = ['video/mp4;codecs=avc1.42E01E,mp4a.40.2', 'video/mp4;codecs=avc1,mp4a.40.2', 'video/mp4',
            'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];
        return list.find((m) => { try { return MediaRecorder.isTypeSupported(m); } catch (e) { return false; } }) || null;
    }

    async function open(files, opts = {}) {
        const { el, canvas, clamp, drawTexts, FONT, TEXT_COLORS } = K();
        injectCss();
        if (!document.createElement('canvas').captureStream || !pickMime()) {
            alert('Este navegador no permite editar videos. Usa Google Chrome (computadora o Android) o Safari actualizado. Mientras tanto puedes subir el video tal cual.');
            return;
        }
        const maxMb = opts.maxMb || 90;

        const S = {
            clips: [], sel: null, selText: null, t: 0, playing: false,
            format: '9:16', fit: 'cover', bgStyle: 'blur', bgColor: '#000000',
            filter: { ...FILTERS[0][1] }, filterName: 'Original', fade: true,
            texts: [], music: null, tab: 'clip', exporting: null,
        };
        const urls = [];
        let ac = null, dest = null;
        const gains = new WeakMap();

        /* ---------- DOM ---------- */
        const cv = el('canvas');
        const overlay = el('div', { style: 'position:absolute;inset:0' });
        const wrap = el('div', { class: 'rye-ve-wrap' }, [cv, overlay]);
        const stage = el('div', { class: 'rye-ve-stage' }, wrap);
        const playBtn = el('button', { type: 'button', onclick: () => (S.playing ? pause() : play()) }, '▶ Reproducir');
        const timeLbl = el('span', { class: 't' });
        const track = el('div', { class: 'rye-ve-track' });
        const textsRow = el('div', { class: 'rye-ve-texts' });
        const head = el('div', { class: 'rye-ve-head' });
        const tl = el('div', { class: 'rye-ve-tl' }, [track, textsRow, head]);
        const tabsBox = el('div', { class: 'rye-ed-tabs' });
        const panel = el('div', { class: 'rye-ed-panel' });
        const fileIn = el('input', { type: 'file', accept: 'video/*,image/*', multiple: true, style: 'display:none', onchange: (e) => { addFiles([...e.target.files]); e.target.value = ''; } });
        const musicIn = el('input', { type: 'file', accept: 'audio/*', style: 'display:none', onchange: (e) => { if (e.target.files[0]) setMusic(e.target.files[0]); e.target.value = ''; } });
        const exportBtn = el('button', { type: 'button', class: 'ok', onclick: () => exportVideo() }, '✔ Exportar y usar');
        const root = el('div', { class: 'rye-ed', role: 'dialog', 'aria-label': 'Editor de video' }, [
            el('div', { class: 'rye-ed-top' }, [
                el('button', { type: 'button', onclick: () => close() }, '✕ Cancelar'),
                el('h2', { text: 'Editor de video' }),
                exportBtn,
            ]),
            el('div', { class: 'rye-ve-main' }, [stage, el('div', { class: 'rye-ve-bar' }, [playBtn, timeLbl,
                el('button', { type: 'button', title: 'Al inicio', onclick: () => seek(0) }, '⏮'),
                el('button', { type: 'button', title: 'Dividir aquí', onclick: () => split() }, '✂ Dividir aquí')]), tl]),
            el('div', { class: 'rye-ed-bottom' }, [tabsBox, panel]),
            fileIn, musicIn,
        ]);
        root.__state = S;   // para diagnosticar desde la consola
        document.body.append(root);
        const prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        /* ---------- clips ---------- */
        const len = (c) => (c.out - c.in) / c.speed;
        const total = () => S.clips.reduce((s, c) => s + len(c), 0);
        const startOf = (c) => { let s = 0; for (const x of S.clips) { if (x === c) return s; s += len(x); } return s; };
        function locate(t) {
            let s = 0;
            for (let i = 0; i < S.clips.length; i++) {
                const c = S.clips[i], l = len(c);
                if (t < s + l || i === S.clips.length - 1) return { c, i, start: s, local: c.in + clamp(t - s, 0, l) * c.speed };
                s += l;
            }
            return null;
        }

        function makeVideo(url) {
            const v = document.createElement('video');
            v.src = url; v.playsInline = true; v.preload = 'auto'; v.crossOrigin = 'anonymous';
            v.setAttribute('playsinline', '');
            return v;
        }
        // Mover un video a un segundo y esperar el cuadro (con límite de tiempo: nunca se queda colgado)
        async function seekVideo(v, t) {
            if (v.readyState < 1) { try { await Promise.race([waitFor(v, 'loadedmetadata'), new Promise((r) => setTimeout(r, 4000))]); } catch (e) { return; } }
            const p = waitFor(v, 'seeked').catch(() => {});
            v.currentTime = t;
            await Promise.race([p, new Promise((r) => setTimeout(r, 1500))]);
        }
        function waitFor(elm, ev) { return new Promise((res, rej) => { const ok = () => { off(); res(); }; const ko = () => { off(); rej(new Error('media')); }; const off = () => { elm.removeEventListener(ev, ok); elm.removeEventListener('error', ko); }; elm.addEventListener(ev, ok, { once: true }); elm.addEventListener('error', ko, { once: true }); }); }

        async function thumbOf(src, w, h) {
            const c = canvas(96, 96);
            const k = Math.max(96 / w, 96 / h);
            c.getContext('2d').drawImage(src, (96 - w * k) / 2, (96 - h * k) / 2, w * k, h * k);
            return c.toDataURL('image/jpeg', 0.6);
        }

        async function addFiles(list) {
            for (const f of list) {
                const url = URL.createObjectURL(f);
                urls.push(url);
                try {
                    if (f.type.startsWith('image/')) {
                        const img = new Image(); img.src = url; await waitFor(img, 'load');
                        S.clips.push({ id: ++uid, kind: 'image', name: f.name, url, el: img, dur: 30, in: 0, out: 3, speed: 1, vol: 0, zoom: true,
                            w: img.naturalWidth, h: img.naturalHeight, thumb: await thumbOf(img, img.naturalWidth, img.naturalHeight) });
                    } else {
                        const v = makeVideo(url);
                        await waitFor(v, 'loadedmetadata');
                        if (!isFinite(v.duration) || !v.videoWidth) throw new Error('media');
                        v.currentTime = Math.min(0.3, v.duration / 2); await waitFor(v, 'seeked');
                        S.clips.push({ id: ++uid, kind: 'video', name: f.name, url, el: v, dur: v.duration, in: 0, out: v.duration, speed: 1, vol: 1,
                            w: v.videoWidth, h: v.videoHeight, thumb: await thumbOf(v, v.videoWidth, v.videoHeight) });
                    }
                } catch (e) {
                    alert('«' + f.name + '» no se puede abrir en este navegador (formato no compatible). Prueba con otro video o grábalo de nuevo.');
                }
            }
            if (!S.sel && S.clips.length) S.sel = S.clips[0];
            if (!S.clips.length) { drawAll(); return; }
            // Si el primer clip es vertical/horizontal, elegimos el formato que mejor le queda
            if (S.clips.length && !S.formatChosen) {
                const c = S.clips[0];
                S.format = c.h > c.w * 1.2 ? '9:16' : c.w > c.h * 1.2 ? '16:9' : '1:1';
            }
            resize(); drawAll(); seek(Math.min(S.t, total()));
        }

        function split() {
            const L = locate(S.t);
            if (!L) return;
            const { c, local } = L;
            if (local - c.in < 0.3 || c.out - local < 0.3) { toast('Mueve la línea blanca más adentro del clip para dividirlo.'); return; }
            const copy = { ...c, id: ++uid, in: local };
            if (c.kind === 'video') copy.el = makeVideo(c.url);
            c.out = local;
            S.clips.splice(L.i + 1, 0, copy);
            S.sel = copy;
            drawAll();
        }
        function duplicate(c) {
            const copy = { ...c, id: ++uid };
            if (c.kind === 'video') copy.el = makeVideo(c.url);
            S.clips.splice(S.clips.indexOf(c) + 1, 0, copy);
            S.sel = copy; drawAll();
        }
        function removeClip(c) {
            if (!confirm('¿Quitar este clip del video?')) return;
            pause();
            S.clips = S.clips.filter((x) => x !== c);
            S.sel = S.clips[0] || null;
            S.t = Math.min(S.t, total());
            drawAll(); seek(S.t);
        }
        function moveClip(c, dir) {
            const i = S.clips.indexOf(c), j = i + dir;
            if (j < 0 || j >= S.clips.length) return;
            [S.clips[i], S.clips[j]] = [S.clips[j], S.clips[i]];
            drawAll(); seek(startOf(c));
        }

        /* ---------- música ---------- */
        async function setMusic(f) {
            const url = URL.createObjectURL(f); urls.push(url);
            const a = new Audio(url); a.preload = 'auto';
            try { await waitFor(a, 'loadedmetadata'); } catch (e) { alert('No se pudo abrir esa canción. Usa un MP3.'); return; }
            if (S.music) S.music.el.pause();
            S.music = { name: f.name, el: a, vol: 0.7, offset: 0, dur: a.duration };
            connect(a, S.music.vol);
            drawPanel();
        }

        /* ---------- audio (mezcla del video + música) ---------- */
        function ensureAudio() {
            if (ac) { if (ac.state === 'suspended') ac.resume(); return; }
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            ac = new AC();
            dest = ac.createMediaStreamDestination();
            S.clips.forEach((c) => c.kind === 'video' && connect(c.el, c.vol));
            if (S.music) connect(S.music.el, S.music.vol);
        }
        function connect(media, vol) {
            if (!ac || gains.has(media)) { if (gains.has(media)) gains.get(media).gain.value = vol; return; }
            try {
                const src = ac.createMediaElementSource(media);
                const g = ac.createGain(); g.gain.value = vol;
                src.connect(g); g.connect(dest); g.connect(ac.destination);
                gains.set(media, g);
            } catch (e) { /* ya conectado */ }
        }
        const setVol = (media, v) => { const g = gains.get(media); if (g) g.gain.value = v; else media.volume = v; };

        /* ---------- dibujo ---------- */
        function filterCss(extra) {
            const f = S.filter;
            return `brightness(${f.b}%) contrast(${f.c}%) saturate(${f.s}%) grayscale(${f.g}%)` + (f.sepia ? ` sepia(${f.sepia}%)` : '') + (extra || '');
        }
        function drawSource(ctx, src, sw, sh, W, H, mode, scale = 1) {
            const k = (mode === 'cover' ? Math.max(W / sw, H / sh) : Math.min(W / sw, H / sh)) * scale;
            ctx.drawImage(src, (W - sw * k) / 2, (H - sh * k) / 2, sw * k, sh * k);
        }
        function drawFrame(t) {
            const ctx = cv.getContext('2d');
            const W = cv.width, H = cv.height;
            ctx.fillStyle = S.bgColor; ctx.fillRect(0, 0, W, H);
            const L = locate(t);
            if (L) {
                const { c } = L;
                const src = c.el;
                const zoom = c.kind === 'image' && c.zoom ? 1 + 0.08 * clamp((t - L.start) / Math.max(0.1, len(c)), 0, 1) : 1;
                if (S.fit === 'contain' && S.bgStyle === 'blur') {
                    if (canFilter) { ctx.filter = 'blur(28px) brightness(.65)'; drawSource(ctx, src, c.w, c.h, W, H, 'cover', 1.15); ctx.filter = 'none'; }
                    else { ctx.globalAlpha = 0.35; drawSource(ctx, src, c.w, c.h, W, H, 'cover'); ctx.globalAlpha = 1; }
                }
                if (canFilter) ctx.filter = filterCss();
                drawSource(ctx, src, c.w, c.h, W, H, S.fit, zoom);
                ctx.filter = 'none';
                // Transición suave entre clips
                if (S.fade && L.i > 0) {
                    const into = t - L.start;
                    if (into < 0.3) { ctx.fillStyle = `rgba(0,0,0,${(1 - into / 0.3) * 0.85})`; ctx.fillRect(0, 0, W, H); }
                }
            }
            const active = S.texts.filter((x) => t >= x.start && t <= x.end && !(S.tab === 'texto' && !S.playing && !S.exporting));
            for (const x of active) {
                const into = t - x.start, left = x.end - t;
                const a = x.anim === 'none' ? 1 : clamp(Math.min(into, left) / 0.3, 0, 1);
                ctx.globalAlpha = a;
                const size = x.anim === 'pop' ? x.size * (0.7 + 0.3 * clamp(into / 0.25, 0, 1)) : x.size;
                drawTexts(ctx, [{ ...x, size }], W, H);
                ctx.globalAlpha = 1;
            }
        }

        function resize() {
            const [W, H] = FORMATS[S.format];
            cv.width = W; cv.height = H;
            fit();
        }
        function fit() {
            const r = stage.getBoundingClientRect();
            const [W, H] = FORMATS[S.format];
            const k = Math.min((r.width - 20) / W, (r.height - 20) / H);
            wrap.style.width = Math.max(10, W * k) + 'px';
            wrap.style.height = Math.max(10, H * k) + 'px';
            drawOverlay();
        }
        const ro = new ResizeObserver(() => fit());
        ro.observe(stage);

        /* ---------- reproducción ---------- */
        let raf = 0, active = null, waiting = false, lastTs = 0;
        function stopMedia() {
            S.clips.forEach((c) => c.kind === 'video' && !c.el.paused && c.el.pause());
            if (S.music) S.music.el.pause();
        }
        function play() {
            if (!S.clips.length) return;
            ensureAudio();
            if (S.t >= total() - 0.05) S.t = 0;
            S.playing = true; active = null; lastTs = performance.now();
            playBtn.textContent = '⏸ Pausa';
            if (S.music) { S.music.el.currentTime = (S.music.offset + S.t) % Math.max(1, S.music.dur); S.music.el.play().catch(() => {}); }
            drawOverlay();
            raf = requestAnimationFrame(loop);
        }
        function pause() {
            S.playing = false; cancelAnimationFrame(raf); stopMedia();
            playBtn.textContent = '▶ Reproducir';
            drawOverlay(); updateHead();
        }
        function loop(ts) {
            if (!S.playing) return;
            const L = locate(S.t);
            if (!L) { pause(); return; }
            const { c } = L;
            if (active !== c) {
                if (active && active.kind === 'video') active.el.pause();
                active = c;
                if (c.kind === 'video') {
                    waiting = true;
                    if (S.exporting) S.exporting.rec.state === 'recording' && S.exporting.rec.pause();
                    c.el.playbackRate = c.speed;
                    setVol(c.el, c.vol);
                    seekVideo(c.el, L.local).then(() => c.el.play().catch(() => { c.el.muted = true; return c.el.play(); })).catch(() => {}).finally(() => {
                        waiting = false; lastTs = performance.now();
                        if (S.exporting && S.exporting.rec.state === 'paused') S.exporting.rec.resume();
                    });
                } else { lastTs = ts; }
            }
            if (!waiting) {
                if (c.kind === 'video') {
                    const vt = c.el.currentTime;
                    S.t = L.start + (Math.min(vt, c.out) - c.in) / c.speed;
                    if (vt >= c.out - 0.04 || c.el.ended) { c.el.pause(); S.t = L.start + len(c) + 0.0001; }
                } else {
                    S.t += (ts - lastTs) / 1000; lastTs = ts;
                }
                if (S.music && S.music.el.paused === false && Math.abs(S.music.el.currentTime - ((S.music.offset + S.t) % S.music.dur)) > 0.4) {
                    S.music.el.currentTime = (S.music.offset + S.t) % S.music.dur;
                }
            }
            if (S.t >= total()) {
                S.t = total(); drawFrame(S.t - 0.001);
                if (S.exporting) { finishExport(); return; }
                pause(); seek(0); return;
            }
            drawFrame(S.t);
            updateHead();
            if (S.exporting) S.exporting.progress(S.t / total());
            raf = requestAnimationFrame(loop);
        }
        async function seek(t) {
            S.t = clamp(t, 0, total());
            const wasPlaying = S.playing;
            if (wasPlaying) pause();
            const L = locate(S.t);
            if (L && L.c.kind === 'video') {
                await seekVideo(L.c.el, Math.min(L.local, L.c.out - 0.01));
            }
            drawFrame(S.t); updateHead(); drawOverlay();
            if (wasPlaying) play();
        }

        /* ---------- línea de tiempo ---------- */
        const PX = () => Math.max(28, Math.min(80, (tl.clientWidth - 90) / Math.max(1, total())));
        function drawTimeline() {
            const px = PX();
            track.replaceChildren(
                ...S.clips.map((c) => {
                    const b = el('div', { class: 'rye-ve-clip' + (S.sel === c ? ' sel' : ''), title: c.name, style: `width:${Math.max(40, len(c) * px - 3)}px;background-image:url(${c.thumb})`,
                        onclick: (e) => { e.stopPropagation(); S.sel = c; S.tab = 'clip'; drawAll(); seek(startOf(c)); } }, el('span', { text: (c.kind === 'image' ? '🖼 ' : '') + fmtT(len(c)) }));
                    return b;
                }),
                el('button', { type: 'button', class: 'rye-ve-add', title: 'Agregar video o foto', onclick: (e) => { e.stopPropagation(); fileIn.click(); } }, '+'),
            );
            textsRow.replaceChildren(...S.texts.map((x) => el('div', {
                class: 'rye-ve-tbar' + (S.selText === x ? ' sel' : ''), style: `left:${x.start * px}px;width:${Math.max(24, (x.end - x.start) * px)}px`,
                onclick: (e) => { e.stopPropagation(); S.selText = x; S.tab = 'texto'; drawAll(); seek(x.start + 0.05); },
            }, x.text.split('\n')[0] || 'Texto')));
            textsRow.style.width = total() * px + 'px';
            updateHead();
        }
        tl.addEventListener('click', (e) => {
            if (e.target !== tl && e.target !== track && e.target !== textsRow) return;
            const r = track.getBoundingClientRect();
            seek((e.clientX - r.left) / PX());
        });
        function updateHead() {
            head.style.left = 12 + S.t * PX() + 'px';
            timeLbl.textContent = fmtT(S.t) + ' / ' + fmtT(total());
        }

        /* ---------- textos arrastrables sobre el video ---------- */
        function drawOverlay() {
            overlay.replaceChildren();
            if (S.tab !== 'texto' || S.playing || S.exporting) return;
            const cw = wrap.clientWidth;
            S.texts.filter((x) => S.t >= x.start && S.t <= x.end).forEach((x) => {
                const d = el('div', { class: 'rye-ed-txt' + (S.selText === x ? ' sel' : '') }, x.text || ' ');
                Object.assign(d.style, { left: x.x * 100 + '%', top: x.y * 100 + '%', fontSize: x.size * cw + 'px', fontWeight: x.bold ? 800 : 500, color: x.color,
                    background: x.bg || 'transparent', fontFamily: FONT, textShadow: x.bg ? 'none' : '0 1px 4px rgba(0,0,0,.45)' });
                d.addEventListener('pointerdown', (e) => {
                    e.preventDefault();
                    if (S.selText !== x) { S.selText = x; drawPanel(); drawOverlay(); drawTimeline(); return; }
                    const r = wrap.getBoundingClientRect();
                    const move = (ev) => { x.x = clamp((ev.clientX - r.left) / r.width, 0.03, 0.97); x.y = clamp((ev.clientY - r.top) / r.height, 0.03, 0.97); d.style.left = x.x * 100 + '%'; d.style.top = x.y * 100 + '%'; };
                    const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
                    window.addEventListener('pointermove', move); window.addEventListener('pointerup', up);
                });
                overlay.append(d);
            });
        }

        /* ---------- paneles ---------- */
        const TABS = [['clip', '🎬 Clip'], ['texto', '🔤 Texto'], ['musica', '🎵 Música'], ['filtros', '🎨 Filtros'], ['formato', '📐 Formato']];
        function drawTabs() {
            tabsBox.replaceChildren(...TABS.map(([k, l]) => el('button', { type: 'button', class: S.tab === k ? 'on' : '', onclick: () => { S.tab = k; drawTabs(); drawPanel(); drawOverlay(); drawFrame(S.t); } }, l)));
        }
        const row = (kids) => el('div', { class: 'rye-ed-row' }, kids);
        const hint = (t) => el('p', { class: 'rye-ed-hint', text: t });
        const range = (label, min, max, step, value, oninput, fmt) => {
            const out = el('b', { text: fmt ? fmt(value) : value, style: 'min-width:46px;text-align:right' });
            return el('label', {}, [el('span', { text: label }), el('input', { type: 'range', min, max, step, value, oninput: (e) => { const v = +e.target.value; out.textContent = fmt ? fmt(v) : v; oninput(v); } }), out]);
        };
        const sw = (color, on, pick) => el('button', { type: 'button', class: 'rye-ed-sw' + (on ? ' on' : ''), style: `background:${color}`, title: color, onclick: pick });

        function drawPanel() {
            const P = [];
            if (!S.clips.length) {
                P.push(el('div', { class: 'rye-ve-empty' }, [el('p', { text: 'Agrega videos o fotos para empezar. Puedes juntar varios en un solo video.' }), el('button', { type: 'button', class: 'ok', onclick: () => fileIn.click() }, '+ Agregar videos o fotos')]));
                panel.replaceChildren(...P); return;
            }
            if (S.tab === 'clip') {
                const c = S.sel && S.clips.includes(S.sel) ? S.sel : (S.sel = S.clips[0]);
                P.push(hint('Clip ' + (S.clips.indexOf(c) + 1) + ' de ' + S.clips.length + ' · ' + c.name + ' · dura ' + fmtT(len(c))));
                if (c.kind === 'video') {
                    P.push(row([
                        range('Empieza en', 0, c.dur, 0.1, c.in, (v) => { c.in = Math.min(v, c.out - 0.3); drawTimeline(); seek(startOf(c)); }, fmtT),
                        range('Termina en', 0, c.dur, 0.1, c.out, (v) => { c.out = Math.max(v, c.in + 0.3); drawTimeline(); seek(startOf(c) + len(c) - 0.05); }, fmtT),
                    ]));
                    P.push(row([el('span', { style: 'color:#94a3b8;width:92px', text: 'Velocidad' }), ...SPEEDS.map((s) => el('button', { type: 'button', class: c.speed === s ? 'on' : '', onclick: () => { c.speed = s; drawAll(); seek(startOf(c)); } }, s + 'x'))]));
                    P.push(row([range('Volumen', 0, 100, 1, Math.round(c.vol * 100), (v) => { c.vol = v / 100; setVol(c.el, c.vol); }, (v) => v + '%'),
                        el('button', { type: 'button', class: c.vol === 0 ? 'on' : '', onclick: () => { c.vol = c.vol === 0 ? 1 : 0; setVol(c.el, c.vol); drawPanel(); } }, c.vol === 0 ? '🔇 Sin sonido' : '🔊 Silenciar')]));
                } else {
                    P.push(row([range('Duración', 1, 15, 0.5, c.out - c.in, (v) => { c.out = c.in + v; drawTimeline(); }, (v) => v + ' s'),
                        el('label', { style: 'flex:none' }, [el('input', { type: 'checkbox', checked: c.zoom, onchange: (e) => { c.zoom = e.target.checked; drawFrame(S.t); } }), el('span', { style: 'width:auto;color:#e2e8f0', text: 'Zoom suave' })])]));
                }
                P.push(row([
                    el('button', { type: 'button', onclick: () => moveClip(c, -1), disabled: S.clips.indexOf(c) === 0 }, '← Mover'),
                    el('button', { type: 'button', onclick: () => moveClip(c, 1), disabled: S.clips.indexOf(c) === S.clips.length - 1 }, 'Mover →'),
                    el('button', { type: 'button', onclick: () => split() }, '✂ Dividir aquí'),
                    el('button', { type: 'button', onclick: () => duplicate(c) }, '⧉ Duplicar'),
                    el('button', { type: 'button', onclick: () => removeClip(c) }, '🗑 Quitar clip'),
                    el('button', { type: 'button', onclick: () => fileIn.click() }, '+ Agregar más'),
                ]));
                P.push(row(el('label', { style: 'flex:none' }, [el('input', { type: 'checkbox', checked: S.fade, onchange: (e) => { S.fade = e.target.checked; } }), el('span', { style: 'width:auto;color:#e2e8f0', text: 'Transición suave entre clips' })])));
                P.push(hint('Toca un clip abajo para elegirlo. Para cortar un pedazo del medio: pon la línea blanca, "Dividir aquí" y quita la parte que no quieres.'));
            }
            if (S.tab === 'texto') {
                P.push(row([
                    el('button', { type: 'button', class: 'ok', onclick: () => addText('Bs 350', '#ffffff', '#dc2626') }, '+ Agregar texto'),
                    el('button', { type: 'button', onclick: () => addText('¡NUEVO!', '#111827', '#facc15') }, '+ "¡NUEVO!"'),
                    el('button', { type: 'button', onclick: () => addText('Pide el tuyo por WhatsApp', '#ffffff', null, 0.055) }, '+ "Pide por WhatsApp"'),
                ]));
                const x = S.selText && S.texts.includes(S.selText) ? S.selText : null;
                if (x) {
                    P.push(el('textarea', { oninput: (e) => { x.text = e.target.value; drawOverlay(); drawTimeline(); } }, x.text));
                    P.push(row([
                        range('Aparece', 0, total(), 0.1, x.start, (v) => { x.start = Math.min(v, x.end - 0.3); drawTimeline(); seek(x.start + 0.05); }, fmtT),
                        range('Se va', 0, total(), 0.1, x.end, (v) => { x.end = Math.max(v, x.start + 0.3); drawTimeline(); }, fmtT),
                    ]));
                    P.push(row([
                        el('button', { type: 'button', onclick: () => { x.start = S.t; x.end = Math.max(x.end, x.start + 0.5); drawPanel(); drawTimeline(); } }, '⇤ Aparece aquí'),
                        el('button', { type: 'button', onclick: () => { x.end = Math.max(S.t, x.start + 0.3); drawPanel(); drawTimeline(); } }, '⇥ Se va aquí'),
                        el('button', { type: 'button', onclick: () => { x.start = 0; x.end = total(); drawPanel(); drawTimeline(); } }, 'Todo el video'),
                    ]));
                    P.push(row([range('Tamaño', 3, 20, 0.5, x.size * 100, (v) => { x.size = v / 100; drawOverlay(); }),
                        el('button', { type: 'button', class: x.bold ? 'on' : '', onclick: () => { x.bold = !x.bold; drawPanel(); drawOverlay(); } }, 'Negrita'),
                        ...[['fade', 'Aparecer suave'], ['pop', 'Saltar'], ['none', 'Sin animación']].map(([k, l]) => el('button', { type: 'button', class: x.anim === k ? 'on' : '', onclick: () => { x.anim = k; drawPanel(); } }, l))]));
                    P.push(row([el('span', { style: 'color:#94a3b8;width:92px', text: 'Letra' }), ...TEXT_COLORS.map((c) => sw(c, x.color === c, () => { x.color = c; drawPanel(); drawOverlay(); })),
                        el('input', { type: 'color', value: x.color, oninput: (e) => { x.color = e.target.value; drawOverlay(); } })]));
                    P.push(row([el('span', { style: 'color:#94a3b8;width:92px', text: 'Fondo letra' }), el('button', { type: 'button', class: !x.bg ? 'on' : '', onclick: () => { x.bg = null; drawPanel(); drawOverlay(); } }, 'Sin fondo'),
                        ...['#dc2626', '#facc15', '#111827', '#ffffff', '#16a34a', '#2563eb'].map((c) => sw(c, x.bg === c, () => { x.bg = c; drawPanel(); drawOverlay(); }))]));
                    P.push(row(el('button', { type: 'button', onclick: () => { S.texts = S.texts.filter((y) => y !== x); S.selText = null; drawAll(); } }, '🗑 Quitar este texto')));
                    P.push(hint('Arrastra el texto sobre el video para moverlo. La barra morada de abajo muestra cuándo aparece.'));
                } else {
                    P.push(hint(S.texts.length ? 'Toca una barra morada (abajo) o un texto del video para editarlo.' : 'Pon el precio, el modelo o "Pide por WhatsApp". Aparece desde la línea blanca.'));
                }
            }
            if (S.tab === 'musica') {
                if (S.music) {
                    P.push(hint('🎵 ' + S.music.name));
                    P.push(row([range('Volumen música', 0, 100, 1, Math.round(S.music.vol * 100), (v) => { S.music.vol = v / 100; setVol(S.music.el, S.music.vol); }, (v) => v + '%'),
                        range('Empieza la canción en', 0, Math.max(0, S.music.dur - 1), 0.5, S.music.offset, (v) => { S.music.offset = v; }, fmtT)]));
                    P.push(row([el('button', { type: 'button', onclick: () => musicIn.click() }, 'Cambiar canción'), el('button', { type: 'button', onclick: () => { S.music.el.pause(); S.music = null; drawPanel(); } }, '🗑 Quitar música'),
                        el('button', { type: 'button', onclick: () => { S.clips.forEach((c) => { if (c.kind === 'video') { c.vol = 0; setVol(c.el, 0); } }); drawPanel(); } }, '🔇 Silenciar los videos')]));
                } else {
                    P.push(row(el('button', { type: 'button', class: 'ok', onclick: () => musicIn.click() }, '+ Agregar música (MP3)')));
                }
                P.push(hint('Usa música propia o sin derechos de autor: Facebook y TikTok pueden silenciar videos con canciones comerciales.'));
            }
            if (S.tab === 'filtros') {
                if (!canFilter) P.push(hint('Este navegador no aplica filtros al exportar. Usa Google Chrome para tener filtros.'));
                P.push(row(FILTERS.map(([l, v]) => el('button', { type: 'button', class: S.filterName === l ? 'on' : '', onclick: () => { S.filter = { ...v }; S.filterName = l; drawPanel(); drawFrame(S.t); } }, l))));
                P.push(row([range('Brillo', 50, 150, 1, S.filter.b, (v) => { S.filter.b = v; S.filterName = ''; drawFrame(S.t); }, (v) => v + '%'),
                    range('Contraste', 50, 150, 1, S.filter.c, (v) => { S.filter.c = v; S.filterName = ''; drawFrame(S.t); }, (v) => v + '%')]));
                P.push(row(range('Saturación', 0, 200, 1, S.filter.s, (v) => { S.filter.s = v; S.filterName = ''; drawFrame(S.t); }, (v) => v + '%')));
            }
            if (S.tab === 'formato') {
                P.push(row([el('span', { style: 'color:#94a3b8;width:92px', text: 'Tamaño' }), ...[['9:16', '9:16 TikTok / Reels'], ['1:1', '1:1 Cuadrado'], ['4:5', '4:5 Facebook'], ['16:9', '16:9 YouTube']].map(([k, l]) =>
                    el('button', { type: 'button', class: S.format === k ? 'on' : '', onclick: () => { S.format = k; S.formatChosen = true; resize(); drawPanel(); drawFrame(S.t); } }, l))]));
                P.push(row([el('span', { style: 'color:#94a3b8;width:92px', text: 'Encuadre' }),
                    el('button', { type: 'button', class: S.fit === 'cover' ? 'on' : '', onclick: () => { S.fit = 'cover'; drawPanel(); drawFrame(S.t); } }, 'Llenar (recorta bordes)'),
                    el('button', { type: 'button', class: S.fit === 'contain' ? 'on' : '', onclick: () => { S.fit = 'contain'; drawPanel(); drawFrame(S.t); } }, 'Ajustar (se ve todo)')]));
                if (S.fit === 'contain') {
                    P.push(row([el('span', { style: 'color:#94a3b8;width:92px', text: 'Fondo' }),
                        el('button', { type: 'button', class: S.bgStyle === 'blur' ? 'on' : '', onclick: () => { S.bgStyle = 'blur'; drawPanel(); drawFrame(S.t); } }, 'Desenfocado'),
                        ...BG_SWATCHES.map((c) => sw(c, S.bgStyle === 'color' && S.bgColor === c, () => { S.bgStyle = 'color'; S.bgColor = c; drawPanel(); drawFrame(S.t); })),
                        el('input', { type: 'color', value: S.bgColor, oninput: (e) => { S.bgStyle = 'color'; S.bgColor = e.target.value; drawFrame(S.t); } })]));
                }
            }
            panel.replaceChildren(...P);
        }
        function addText(text, color, bg, size = 0.07) {
            const x = { text, color, bg, size, bold: true, anim: 'fade', x: 0.5, y: 0.8, start: S.t, end: Math.min(total(), S.t + 3) };
            if (x.end - x.start < 0.5) { x.start = Math.max(0, total() - 3); x.end = total(); }
            S.texts.push(x); S.selText = x; S.tab = 'texto';
            drawAll(); seek(x.start + 0.05);
        }
        function drawAll() { drawTabs(); drawPanel(); drawTimeline(); drawOverlay(); exportBtn.disabled = !S.clips.length; }

        function toast(msg) {
            const t = el('div', { class: 'rye-ed-busy', style: 'background:rgba(2,6,23,.75)' }, msg);
            stage.append(t); setTimeout(() => t.remove(), 2200);
        }

        /* ---------- exportar ---------- */
        async function exportVideo() {
            if (!S.clips.length) return;
            const dur = total();
            if (dur > 180) { alert('El video dura ' + fmtT(dur) + '. Para la página el máximo es 3 minutos: recorta o quita clips.'); return; }
            pause();
            ensureAudio();
            const mime = pickMime();
            const stream = cv.captureStream(30);
            if (dest) dest.stream.getAudioTracks().forEach((tr) => stream.addTrack(tr));
            const vbr = Math.round(Math.min(4.5e6, Math.max(8e5, (maxMb * 0.85 * 8e6) / Math.max(1, dur) - 128000)));
            let rec;
            try { rec = new MediaRecorder(stream, { mimeType: mime, videoBitsPerSecond: vbr, audioBitsPerSecond: 128000 }); } catch (e) {
                try { rec = new MediaRecorder(stream); } catch (e2) { alert('No se pudo empezar a grabar el video en este navegador.'); return; }
            }
            const chunks = [];
            rec.ondataavailable = (e) => e.data && e.data.size && chunks.push(e.data);

            const bar = el('i');
            const pct = el('p', { style: 'font-size:22px;font-weight:800;margin:0', text: '0%' });
            const prog = el('div', { class: 'rye-ve-prog' }, [
                el('p', { style: 'font-size:18px;font-weight:700;margin:0', text: 'Creando tu video…' }), pct, el('div', { class: 'b' }, bar),
                el('p', { class: 'rye-ed-hint', text: 'Tarda lo mismo que dura el video. No cambies de pestaña ni apagues la pantalla.' }),
                el('button', { type: 'button', onclick: () => cancelExport() }, 'Cancelar'),
            ]);
            root.append(prog);

            S.selText = null; if (S.tab === 'texto') S.tab = 'clip';
            drawAll();
            // Portada: un cuadro del comienzo
            await seek(Math.min(0.4, dur / 2));
            const poster = await new Promise((r) => cv.toBlob(r, 'image/jpeg', 0.85));
            let cancelled = false;
            const onVis = () => {
                if (!S.exporting) return;
                if (document.hidden && S.playing) { S.exporting.hiddenPause = true; S.playing = false; cancelAnimationFrame(raf); stopMedia(); rec.state === 'recording' && rec.pause(); }
                else if (!document.hidden && S.exporting.hiddenPause) { S.exporting.hiddenPause = false; rec.state === 'paused' && rec.resume(); S.playing = true; active = null; if (S.music) S.music.el.play().catch(() => {}); raf = requestAnimationFrame(loop); }
            };
            document.addEventListener('visibilitychange', onVis);
            const done = new Promise((resolve) => { rec.onstop = resolve; });
            // Si se queda trabado (sin avanzar 10 s con la pantalla visible), se cancela con un aviso
            let lastT = -1, lastMove = Date.now(), stuck = false;
            const watchdog = setInterval(() => {
                if (document.hidden || !S.exporting) { lastMove = Date.now(); return; }
                if (S.t !== lastT) { lastT = S.t; lastMove = Date.now(); return; }
                if (Date.now() - lastMove > 10000) { stuck = true; cancelExport(); }
            }, 1000);
            S.exporting = { rec, progress: (p) => { const v = Math.round(clamp(p, 0, 1) * 100); bar.style.width = v + '%'; pct.textContent = v + '%'; } };
            function cancelExport() { cancelled = true; finishExport(); }
            finishExport = () => {
                S.playing = false; cancelAnimationFrame(raf); stopMedia();
                if (rec.state !== 'inactive') rec.stop();
            };
            S.t = 0; active = null;
            await seek(0);
            rec.start(500);
            play();
            await done;
            clearInterval(watchdog);
            document.removeEventListener('visibilitychange', onVis);
            S.exporting = null; prog.remove();
            playBtn.textContent = '▶ Reproducir';
            if (cancelled) {
                finishExport = () => {};
                if (stuck) alert('El video se trabó al crearlo (puede que un clip esté dañado). Prueba quitando el último clip agregado o usa Google Chrome actualizado.');
                return;
            }
            const type = (rec.mimeType || mime || 'video/webm').split(';')[0];
            const blob = new Blob(chunks, { type });
            if (!blob.size) { alert('El video salió vacío. Intenta de nuevo con Google Chrome.'); return; }
            if (blob.size > maxMb * 1048576) { alert('El video quedó muy pesado (' + Math.round(blob.size / 1048576) + ' MB). Hazlo más corto.'); return; }
            const ext = type.includes('mp4') ? 'mp4' : 'webm';
            cleanup();
            opts.onDone && opts.onDone(blob, poster, 'video-' + new Date().toISOString().slice(0, 10) + '.' + ext);
        }
        let finishExport = () => {};

        /* ---------- cerrar ---------- */
        function cleanup() {
            pause(); ro.disconnect();
            document.removeEventListener('keydown', onKey);
            S.clips.forEach((c) => { if (c.kind === 'video') { c.el.removeAttribute('src'); c.el.load(); } });
            if (S.music) S.music.el.pause();
            urls.forEach((u) => URL.revokeObjectURL(u));
            if (ac) ac.close().catch(() => {});
            document.body.style.overflow = prevOverflow;
            root.remove();
        }
        function close() {
            if (S.exporting) return;
            if (S.clips.length && !confirm('¿Salir del editor? Se pierde lo que editaste.')) return;
            cleanup();
            opts.onCancel && opts.onCancel();
        }
        const onKey = (e) => {
            if (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'INPUT') return;
            if (e.key === ' ') { e.preventDefault(); S.playing ? pause() : play(); }
            if (e.key === 'Escape') close();
        };
        document.addEventListener('keydown', onKey);

        resize(); drawAll(); drawFrame(0);
        if (files && files.length) await addFiles([...files]);
    }

    window.RyEVideoEditor = { open, supported: () => !!(document.createElement('canvas').captureStream && pickMime()) };
})();
