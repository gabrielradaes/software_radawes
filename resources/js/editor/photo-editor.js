/*
 * Editor de fotos de RyE (todo en el navegador, sin servicios externos).
 * Recortar · girar · voltear · brillo/contraste/saturación/calidez · filtros
 * Fondo: quitar automático (fotos con fondo liso), tocar para quitar, pincel borrar/recuperar,
 *        fondo blanco / de color / transparente, sombra suave
 * Textos encima (arrastrables) · Deshacer
 *
 * Uso: RyEPhotoEditor.open(fileOBlob, { title, onDone(blob, nombre) })
 */
(function () {
    'use strict';
    if (window.RyEPhotoEditor) return;

    const MAX = 1600;
    const FONT = 'Inter, "Segoe UI", Arial, sans-serif';
    const PRESETS = [
        ['Original', {}],
        ['Vivo', { contrast: 15, saturation: 35 }],
        ['Brillante', { brightness: 18, contrast: 6 }],
        ['Cálido', { warmth: 30, saturation: 10 }],
        ['Frío', { warmth: -30 }],
        ['Dramático', { contrast: 35, saturation: -10, brightness: -5 }],
        ['Suave', { contrast: -15, brightness: 8, saturation: -10 }],
        ['Blanco y negro', { gray: true, contrast: 10 }],
    ];
    const ASPECTS = [['Libre', 0], ['1:1', 1], ['4:5', 4 / 5], ['9:16', 9 / 16], ['16:9', 16 / 9], ['3:4', 3 / 4]];
    const SWATCHES = ['#ffffff', '#f5f5f4', '#e5e7eb', '#111827', '#fde68a', '#fbcfe8', '#bfdbfe', '#bbf7d0', '#78350f', '#b91c1c', '#1d4ed8', '#6d28d9'];
    const TEXT_COLORS = ['#ffffff', '#111827', '#facc15', '#ef4444', '#22c55e', '#3b82f6', '#ec4899', '#f97316'];

    /* ---------- utilidades ---------- */
    const el = (tag, attrs = {}, kids = []) => {
        const e = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs)) {
            if (k === 'class') e.className = v;
            else if (k === 'text') e.textContent = v;
            else if (k.startsWith('on')) e.addEventListener(k.slice(2), v);
            else if (v !== false && v != null) e.setAttribute(k, v === true ? '' : v);
        }
        (Array.isArray(kids) ? kids : [kids]).forEach((c) => c != null && e.append(c));
        return e;
    };
    const canvas = (w, h) => { const c = document.createElement('canvas'); c.width = Math.max(1, Math.round(w)); c.height = Math.max(1, Math.round(h)); return c; };
    const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
    const hexRgb = (h) => [parseInt(h.slice(1, 3), 16), parseInt(h.slice(3, 5), 16), parseInt(h.slice(5, 7), 16)];

    function loadImage(src) {
        return new Promise((res, rej) => {
            const url = typeof src === 'string' ? src : URL.createObjectURL(src);
            const img = new Image();
            img.onload = () => res({ img, url });
            img.onerror = () => rej(new Error('formato'));
            img.src = url;
        });
    }

    function injectCss() {
        if (document.getElementById('rye-ed-css')) return;
        const css = `
        .rye-ed{position:fixed;inset:0;z-index:9999;background:#0b1120;color:#e2e8f0;display:flex;flex-direction:column;font:14px/1.4 ${FONT};-webkit-user-select:none;user-select:none}
        .rye-ed *{box-sizing:border-box}
        .rye-ed button{font:inherit;cursor:pointer;border:0;border-radius:10px;padding:9px 14px;background:#1e293b;color:#e2e8f0;min-height:40px}
        .rye-ed button:hover{background:#334155}
        .rye-ed button.on{background:#f8fafc;color:#0f172a}
        .rye-ed button.ok{background:#16a34a;color:#fff;font-weight:700}
        .rye-ed button.ok:hover{background:#15803d}
        .rye-ed button:disabled{opacity:.4;cursor:default}
        .rye-ed-top{display:flex;align-items:center;gap:8px;padding:8px 12px;border-bottom:1px solid #1e293b}
        .rye-ed-top h2{font-size:16px;margin:0 auto 0 4px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .rye-ed-stage{flex:1;min-height:0;display:flex;align-items:center;justify-content:center;padding:12px;position:relative;overflow:hidden;touch-action:none}
        .rye-ed-wrap{position:relative;flex:none;box-shadow:0 10px 40px rgba(0,0,0,.5)}
        .rye-ed-wrap canvas{display:block;width:100%;height:100%}
        .rye-ed-checker{background-color:#fff;background-image:linear-gradient(45deg,#d4d4d8 25%,transparent 25%),linear-gradient(-45deg,#d4d4d8 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#d4d4d8 75%),linear-gradient(-45deg,transparent 75%,#d4d4d8 75%);background-size:20px 20px;background-position:0 0,0 10px,10px -10px,-10px 0}
        .rye-ed-crop{position:absolute;border:2px solid #fff;box-shadow:0 0 0 9999px rgba(0,0,0,.55);cursor:move;touch-action:none}
        .rye-ed-crop::before,.rye-ed-crop::after{content:"";position:absolute;inset:33.33% 0;border-top:1px solid rgba(255,255,255,.45);border-bottom:1px solid rgba(255,255,255,.45);pointer-events:none}
        .rye-ed-crop::after{inset:0 33.33%;border:0;border-left:1px solid rgba(255,255,255,.45);border-right:1px solid rgba(255,255,255,.45)}
        .rye-ed-h{position:absolute;width:26px;height:26px;background:#fff;border-radius:50%;border:3px solid #0ea5e9;touch-action:none}
        .rye-ed-txt{position:absolute;transform:translate(-50%,-50%);white-space:pre;text-align:center;line-height:1.15;cursor:move;padding:.15em .35em;border-radius:.25em;touch-action:none;outline:2px dashed transparent;outline-offset:4px}
        .rye-ed-txt.sel{outline-color:#38bdf8}
        .rye-ed-bottom{border-top:1px solid #1e293b;background:#0f172a}
        .rye-ed-tabs{display:flex;gap:6px;overflow-x:auto;padding:8px 10px;scrollbar-width:none}
        .rye-ed-tabs button{flex:none}
        .rye-ed-panel{padding:4px 12px 14px;max-height:34vh;overflow-y:auto}
        .rye-ed-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:6px 0}
        .rye-ed-row label{display:flex;align-items:center;gap:8px;flex:1 1 260px}
        .rye-ed-row label span{width:92px;flex:none;color:#94a3b8}
        .rye-ed input[type=range]{flex:1;accent-color:#38bdf8;min-width:120px;height:28px}
        .rye-ed input[type=color]{width:44px;height:40px;border:0;padding:0;background:none;border-radius:8px}
        .rye-ed textarea{width:100%;min-height:52px;border-radius:10px;border:1px solid #334155;background:#020617;color:#fff;padding:8px;font:inherit;-webkit-user-select:text;user-select:text}
        .rye-ed-sw{width:36px;height:36px;min-height:36px;padding:0;border-radius:50%;border:2px solid #334155}
        .rye-ed-sw.on{border-color:#38bdf8;box-shadow:0 0 0 2px #38bdf8}
        .rye-ed-hint{color:#94a3b8;font-size:13px;margin:4px 0 8px}
        .rye-ed-busy{position:absolute;inset:0;background:rgba(2,6,23,.6);display:flex;align-items:center;justify-content:center;font-weight:600;z-index:5}
        .rye-ed-brush{position:absolute;border:2px solid #fff;border-radius:50%;pointer-events:none;box-shadow:0 0 0 1px #000;transform:translate(-50%,-50%);display:none}
        `;
        document.head.append(el('style', { id: 'rye-ed-css' }, css));
    }

    /* ---------- ajustes de color (rápido, sin depender de ctx.filter) ---------- */
    function applyAdjust(src, a) {
        const out = canvas(src.width, src.height);
        const ctx = out.getContext('2d');
        ctx.drawImage(src, 0, 0);
        const neutral = !a.brightness && !a.contrast && !a.saturation && !a.warmth && !a.gray;
        if (neutral) return out;
        const id = ctx.getImageData(0, 0, out.width, out.height);
        const d = id.data;
        const B = (a.brightness || 0) * 1.3;
        const C = (a.contrast || 0) * 1.6;
        const f = (259 * (C + 255)) / (255 * (259 - C));
        const lut = new Uint8ClampedArray(256);
        for (let i = 0; i < 256; i++) lut[i] = f * (i + B - 128) + 128;
        const S = 1 + (a.saturation || 0) / 100;
        const W = (a.warmth || 0) * 0.5;
        const gray = !!a.gray;
        for (let i = 0; i < d.length; i += 4) {
            let r = lut[d[i]], g = lut[d[i + 1]], b = lut[d[i + 2]];
            const y = 0.299 * r + 0.587 * g + 0.114 * b;
            if (gray) { r = g = b = y; } else {
                if (S !== 1) { r = y + (r - y) * S; g = y + (g - y) * S; b = y + (b - y) * S; }
                if (W) { r += W; b -= W; }
            }
            d[i] = r; d[i + 1] = g; d[i + 2] = b;
        }
        ctx.putImageData(id, 0, 0);
        return out;
    }

    /* ---------- quitar fondo (relleno desde los bordes) ---------- */
    function autoMask(imgCanvas, tol) {
        const w = imgCanvas.width, h = imgCanvas.height, n = w * h;
        const d = imgCanvas.getContext('2d').getImageData(0, 0, w, h).data;
        const T = 12 + tol * 1.1;            // diferencia máxima con el color del fondo
        const L = 6 + tol * 0.35;            // diferencia máxima entre vecinos (sigue degradados suaves)
        const T2 = T * T, L2 = L * L;

        // Colores del fondo: los más repetidos en el borde de la foto
        const bins = new Map();
        const border = [];
        const addB = (i) => { border.push(i); const p = i * 4; const k = (d[p] >> 4) << 8 | (d[p + 1] >> 4) << 4 | (d[p + 2] >> 4); bins.set(k, (bins.get(k) || 0) + 1); };
        for (let x = 0; x < w; x++) { addB(x); addB((h - 1) * w + x); }
        for (let y = 1; y < h - 1; y++) { addB(y * w); addB(y * w + w - 1); }
        const minCount = border.length * 0.03;
        const bgCols = [];
        bins.forEach((c, k) => { if (c >= minCount) bgCols.push([((k >> 8) & 15) * 16 + 8, ((k >> 4) & 15) * 16 + 8, (k & 15) * 16 + 8]); });

        const removed = new Uint8Array(n);
        const refR = new Uint8Array(n), refG = new Uint8Array(n), refB = new Uint8Array(n);
        const q = new Int32Array(n);
        let qh = 0, qt = 0;
        const nearBg = (p) => bgCols.some(([r, g, b]) => (d[p] - r) ** 2 + (d[p + 1] - g) ** 2 + (d[p + 2] - b) ** 2 < T2 * 1.2);
        for (const i of border) {
            const p = i * 4;
            if (!removed[i] && nearBg(p)) { removed[i] = 1; refR[i] = d[p]; refG[i] = d[p + 1]; refB[i] = d[p + 2]; q[qt++] = i; }
        }
        while (qh < qt) {
            const i = q[qh++], p = i * 4, x = i % w;
            const cr = d[p], cg = d[p + 1], cb = d[p + 2], rr = refR[i], rg = refG[i], rb = refB[i];
            const nb = [x > 0 ? i - 1 : -1, x < w - 1 ? i + 1 : -1, i - w, i + w];
            for (const j of nb) {
                if (j < 0 || j >= n || removed[j]) continue;
                const pj = j * 4, r = d[pj], g = d[pj + 1], b = d[pj + 2];
                if ((r - cr) ** 2 + (g - cg) ** 2 + (b - cb) ** 2 < L2 && (r - rr) ** 2 + (g - rg) ** 2 + (b - rb) ** 2 < T2) {
                    removed[j] = 1; refR[j] = rr; refG[j] = rg; refB[j] = rb; q[qt++] = j;
                }
            }
        }

        // Quitar manchitas sueltas que quedaron (se queda con las partes grandes del producto)
        const label = new Int32Array(n);
        const sizes = [0];
        let cur = 0;
        for (let s = 0; s < n; s++) {
            if (removed[s] || label[s]) continue;
            cur++; let size = 0; qh = qt = 0; q[qt++] = s; label[s] = cur;
            while (qh < qt) {
                const i = q[qh++], x = i % w; size++;
                const nb = [x > 0 ? i - 1 : -1, x < w - 1 ? i + 1 : -1, i - w, i + w];
                for (const j of nb) if (j >= 0 && j < n && !removed[j] && !label[j]) { label[j] = cur; q[qt++] = j; }
            }
            sizes.push(size);
        }
        const biggest = Math.max(0, ...sizes);
        const keepMin = Math.max(n * 0.004, biggest * 0.12);
        const alpha = new Uint8ClampedArray(n);
        for (let i = 0; i < n; i++) alpha[i] = !removed[i] && sizes[label[i]] >= keepMin ? 255 : 0;
        return { alpha: soften(alpha, w, h), removedPct: 1 - alpha.reduce((s, v) => s + (v > 127), 0) / n, kept: biggest };
    }

    /* Tocar un punto: quita esa zona del color tocado (huecos entre cordones, sombras…) */
    function regionFrom(imgCanvas, maskCanvas, x0, y0, tol) {
        const w = imgCanvas.width, h = imgCanvas.height, n = w * h;
        const d = imgCanvas.getContext('2d').getImageData(0, 0, w, h).data;
        const mctx = maskCanvas.getContext('2d');
        const m = mctx.getImageData(0, 0, w, h);
        const T = 12 + tol * 1.1, T2 = T * T, L = 6 + tol * 0.35, L2 = L * L;
        const s = Math.round(y0) * w + Math.round(x0);
        if (s < 0 || s >= n) return false;
        const ps = s * 4, rr = d[ps], rg = d[ps + 1], rb = d[ps + 2];
        const seen = new Uint8Array(n), q = new Int32Array(n);
        let qh = 0, qt = 0;
        q[qt++] = s; seen[s] = 1;
        while (qh < qt) {
            const i = q[qh++], p = i * 4, x = i % w;
            m.data[p + 3] = 0;
            const nb = [x > 0 ? i - 1 : -1, x < w - 1 ? i + 1 : -1, i - w, i + w];
            for (const j of nb) {
                if (j < 0 || j >= n || seen[j]) continue;
                const pj = j * 4, r = d[pj], g = d[pj + 1], b = d[pj + 2];
                if ((r - d[p]) ** 2 + (g - d[p + 1]) ** 2 + (b - d[p + 2]) ** 2 < L2 && (r - rr) ** 2 + (g - rg) ** 2 + (b - rb) ** 2 < T2) { seen[j] = 1; q[qt++] = j; }
            }
        }
        mctx.putImageData(m, 0, 0);
        return qt > 0;
    }

    /* Borde suave: achica 1 px (sin halo del fondo) y difumina */
    function soften(alpha, w, h) {
        const n = w * h, er = new Uint8ClampedArray(n);
        for (let i = 0; i < n; i++) {
            const x = i % w;
            er[i] = alpha[i] && (x === 0 || alpha[i - 1]) && (x === w - 1 || alpha[i + 1]) && (i < w || alpha[i - w]) && (i >= n - w || alpha[i + w]) ? 255 : 0;
        }
        let a = er;
        for (let pass = 0; pass < 2; pass++) {
            const b = new Uint8ClampedArray(n);
            for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) {
                let s = 0, c = 0;
                for (let dy = -1; dy <= 1; dy++) { const yy = y + dy; if (yy < 0 || yy >= h) continue; for (let dx = -1; dx <= 1; dx++) { const xx = x + dx; if (xx < 0 || xx >= w) continue; s += a[yy * w + xx]; c++; } }
                b[y * w + x] = s / c;
            }
            a = b;
        }
        return a;
    }

    function alphaToCanvas(alpha, w, h) {
        const c = canvas(w, h), ctx = c.getContext('2d');
        const id = ctx.createImageData(w, h);
        for (let i = 0, p = 0; i < alpha.length; i++, p += 4) { id.data[p] = id.data[p + 1] = id.data[p + 2] = 255; id.data[p + 3] = alpha[i]; }
        ctx.putImageData(id, 0, 0);
        return c;
    }

    function transformCanvas(src, op) {
        const rot = op === 'left' || op === 'right';
        const c = canvas(rot ? src.height : src.width, rot ? src.width : src.height);
        const ctx = c.getContext('2d');
        if (op === 'right') { ctx.translate(c.width, 0); ctx.rotate(Math.PI / 2); }
        if (op === 'left') { ctx.translate(0, c.height); ctx.rotate(-Math.PI / 2); }
        if (op === 'flip') { ctx.translate(c.width, 0); ctx.scale(-1, 1); }
        ctx.drawImage(src, 0, 0);
        return c;
    }

    function drawTexts(ctx, texts, W, H) {
        for (const t of texts) {
            if (!t.text.trim()) continue;
            const size = t.size * W;
            ctx.font = `${t.bold ? 800 : 500} ${size}px ${FONT}`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            const lines = t.text.split('\n');
            const lh = size * 1.15;
            const cx = t.x * W, cy = t.y * H;
            const top = cy - (lines.length * lh) / 2 + lh / 2;
            if (t.bg) {
                const wmax = Math.max(...lines.map((l) => ctx.measureText(l).width));
                const pw = size * 0.35, ph = size * 0.15;
                const rx = cx - wmax / 2 - pw, ry = cy - (lines.length * lh) / 2 - ph, rw = wmax + pw * 2, rh = lines.length * lh + ph * 2;
                ctx.fillStyle = t.bg;
                roundRect(ctx, rx, ry, rw, rh, size * 0.25);
                ctx.fill();
            } else {
                ctx.shadowColor = 'rgba(0,0,0,.45)'; ctx.shadowBlur = size * 0.12; ctx.shadowOffsetY = size * 0.04;
            }
            ctx.fillStyle = t.color;
            lines.forEach((l, i) => ctx.fillText(l, cx, top + i * lh));
            ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0; ctx.shadowOffsetY = 0;
        }
    }
    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
    }

    /* ---------- el editor ---------- */
    async function open(source, opts = {}) {
        injectCss();
        let loaded;
        try { loaded = await loadImage(source); } catch (e) {
            alert('Esta foto no se puede abrir en este navegador (por ejemplo, fotos HEIC de iPhone en Windows). Súbela tal cual o pásala a JPG.');
            return;
        }
        const { img, url } = loaded;
        const scale = Math.min(1, MAX / Math.max(img.naturalWidth, img.naturalHeight));
        let base = canvas(img.naturalWidth * scale, img.naturalHeight * scale);
        base.getContext('2d').drawImage(img, 0, 0, base.width, base.height);
        if (typeof source !== 'string') URL.revokeObjectURL(url);

        const S = {
            tab: 'recortar',
            adjust: { brightness: 0, contrast: 0, saturation: 0, warmth: 0, gray: false },
            preset: 'Original',
            crop: { x: 0, y: 0, w: base.width, h: base.height }, aspect: 0,
            mask: null, bgMode: 'original', bgColor: '#ffffff', shadow: true, tol: 30,
            tool: 'none', brush: 28,
            texts: [], sel: null,
            undo: [],
        };
        let adjCache = null, cutCache = null, raf = 0;
        const name = (source && source.name ? source.name.replace(/\.[^.]+$/, '') : 'foto') + '-editada';

        /* DOM */
        const cv = el('canvas');
        const wrap = el('div', { class: 'rye-ed-wrap' }, cv);
        const brushRing = el('div', { class: 'rye-ed-brush' });
        const stage = el('div', { class: 'rye-ed-stage' }, [wrap, brushRing]);
        const panel = el('div', { class: 'rye-ed-panel' });
        const tabsBox = el('div', { class: 'rye-ed-tabs' });
        const undoBtn = el('button', { type: 'button', title: 'Deshacer', onclick: undo, disabled: true }, '↶ Deshacer');
        const root = el('div', { class: 'rye-ed', role: 'dialog', 'aria-label': 'Editor de fotos' }, [
            el('div', { class: 'rye-ed-top' }, [
                el('button', { type: 'button', onclick: () => close(false) }, '✕ Cancelar'),
                el('h2', { text: opts.title || 'Editar foto' }),
                undoBtn,
                el('button', { type: 'button', class: 'ok', onclick: () => close(true) }, '✔ Listo'),
            ]),
            stage,
            el('div', { class: 'rye-ed-bottom' }, [tabsBox, panel]),
        ]);
        document.body.append(root);
        const prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        const TABS = [['recortar', '✂️ Recortar y girar'], ['ajustes', '☀️ Ajustes'], ['filtros', '🎨 Filtros'], ['fondo', '🪄 Fondo'], ['texto', '🔤 Texto']];
        const drawTabs = () => {
            tabsBox.replaceChildren(...TABS.map(([k, l]) => el('button', { type: 'button', class: S.tab === k ? 'on' : '', onclick: () => { S.tab = k; S.tool = 'none'; drawTabs(); drawPanel(); schedule(); } }, l)));
        };

        function pushUndo() {
            S.undo.push({ base, mask: S.mask && copy(S.mask), crop: { ...S.crop }, bgMode: S.bgMode });
            if (S.undo.length > 6) S.undo.shift();
            undoBtn.disabled = false;
        }
        function undo() {
            const u = S.undo.pop();
            if (!u) return;
            base = u.base; S.mask = u.mask; S.crop = u.crop; S.bgMode = u.bgMode;
            adjCache = cutCache = null;
            undoBtn.disabled = !S.undo.length;
            drawPanel(); schedule();
        }
        const copy = (c) => { const n = canvas(c.width, c.height); n.getContext('2d').drawImage(c, 0, 0); return n; };

        /* Composición */
        const adjusted = () => (adjCache ||= applyAdjust(base, S.adjust));
        function cutout() {
            if (cutCache) return cutCache;
            const a = adjusted();
            const c = copy(a);
            const ctx = c.getContext('2d');
            ctx.globalCompositeOperation = 'destination-in';
            ctx.drawImage(S.mask, 0, 0);
            return (cutCache = c);
        }
        function compose(ctx, W, H, sx, sy, sw, sh, withTexts) {
            ctx.clearRect(0, 0, W, H);
            if (S.mask && S.bgMode !== 'original') {
                if (S.bgMode === 'color') { ctx.fillStyle = S.bgColor; ctx.fillRect(0, 0, W, H); }
                if (S.shadow) { ctx.shadowColor = 'rgba(0,0,0,.28)'; ctx.shadowBlur = W * 0.03; ctx.shadowOffsetY = W * 0.018; }
                ctx.drawImage(cutout(), sx, sy, sw, sh, 0, 0, W, H);
                ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0; ctx.shadowOffsetY = 0;
            } else {
                ctx.drawImage(adjusted(), sx, sy, sw, sh, 0, 0, W, H);
            }
            if (withTexts) drawTexts(ctx, S.texts, W, H);
        }

        /* Vista previa */
        const view = () => (S.tab === 'recortar' ? { x: 0, y: 0, w: base.width, h: base.height } : S.crop);
        function render() {
            raf = 0;
            const v = view();
            const W = Math.round(v.w), H = Math.round(v.h);
            if (cv.width !== W || cv.height !== H) { cv.width = W; cv.height = H; }
            compose(cv.getContext('2d'), W, H, v.x, v.y, v.w, v.h, false);
            wrap.classList.toggle('rye-ed-checker', S.bgMode === 'transparent' && !!S.mask);
            fit();
            drawOverlays();
        }
        const schedule = () => { if (!raf) raf = requestAnimationFrame(render); };
        function fit() {
            const v = view();
            const r = stage.getBoundingClientRect();
            const k = Math.min((r.width - 24) / v.w, (r.height - 24) / v.h);
            wrap.style.width = Math.max(10, v.w * k) + 'px';
            wrap.style.height = Math.max(10, v.h * k) + 'px';
        }
        const ro = new ResizeObserver(() => schedule());
        ro.observe(stage);

        /* Recorte */
        let cropBox = null;
        function drawOverlays() {
            wrap.querySelectorAll('.rye-ed-crop,.rye-ed-txt').forEach((n) => n.remove());
            const cw = wrap.clientWidth;
            if (S.tab === 'recortar') {
                const k = cw / base.width;
                cropBox = el('div', { class: 'rye-ed-crop' });
                Object.assign(cropBox.style, { left: S.crop.x * k + 'px', top: S.crop.y * k + 'px', width: S.crop.w * k + 'px', height: S.crop.h * k + 'px' });
                ['nw', 'ne', 'sw', 'se'].forEach((c) => {
                    const h = el('div', { class: 'rye-ed-h', 'data-c': c });
                    Object.assign(h.style, { [c[0] === 'n' ? 'top' : 'bottom']: '-13px', [c[1] === 'w' ? 'left' : 'right']: '-13px', cursor: c + '-resize' });
                    cropBox.append(h);
                });
                cropBox.addEventListener('pointerdown', cropDown);
                wrap.append(cropBox);
                return;
            }
            S.texts.forEach((t) => {
                const d = el('div', { class: 'rye-ed-txt' + (S.sel === t ? ' sel' : '') }, t.text || ' ');
                Object.assign(d.style, {
                    left: t.x * 100 + '%', top: t.y * 100 + '%', fontSize: t.size * cw + 'px', fontWeight: t.bold ? 800 : 500,
                    color: t.color, background: t.bg || 'transparent', fontFamily: FONT,
                    textShadow: t.bg ? 'none' : '0 1px 4px rgba(0,0,0,.45)', pointerEvents: S.tab === 'texto' ? 'auto' : 'none',
                });
                d.addEventListener('pointerdown', (e) => textDown(e, t, d));
                wrap.append(d);
            });
        }
        function cropDown(e) {
            e.preventDefault(); e.stopPropagation();
            const corner = e.target.dataset.c || null;
            const k = wrap.clientWidth / base.width;
            const start = { ...S.crop }, sx = e.clientX, sy = e.clientY;
            const move = (ev) => {
                const dx = (ev.clientX - sx) / k, dy = (ev.clientY - sy) / k;
                const c = { ...start };
                if (!corner) {
                    c.x = clamp(start.x + dx, 0, base.width - start.w);
                    c.y = clamp(start.y + dy, 0, base.height - start.h);
                } else {
                    const right = start.x + start.w, bottom = start.y + start.h;
                    let nx = corner.includes('w') ? clamp(start.x + dx, 0, right - 40) : start.x;
                    let ny = corner.includes('n') ? clamp(start.y + dy, 0, bottom - 40) : start.y;
                    let nw = corner.includes('w') ? right - nx : clamp(start.w + dx, 40, base.width - start.x);
                    let nh = corner.includes('n') ? bottom - ny : clamp(start.h + dy, 40, base.height - start.y);
                    if (S.aspect) {
                        nh = nw / S.aspect;
                        if (corner.includes('n')) ny = bottom - nh;
                        if (ny < 0 || ny + nh > base.height) { nh = corner.includes('n') ? bottom : base.height - start.y; nw = nh * S.aspect; if (corner.includes('w')) nx = right - nw; if (corner.includes('n')) ny = bottom - nh; }
                    }
                    Object.assign(c, { x: nx, y: ny, w: nw, h: nh });
                }
                S.crop = c;
                Object.assign(cropBox.style, { left: c.x * k + 'px', top: c.y * k + 'px', width: c.w * k + 'px', height: c.h * k + 'px' });
            };
            const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up);
        }
        function setAspect(a) {
            S.aspect = a;
            if (!a) { S.crop = { x: 0, y: 0, w: base.width, h: base.height }; } else {
                let w = base.width, h = w / a;
                if (h > base.height) { h = base.height; w = h * a; }
                S.crop = { x: (base.width - w) / 2, y: (base.height - h) / 2, w, h };
            }
            drawPanel(); schedule();
        }
        function geometry(op) {
            pushUndo();
            base = transformCanvas(base, op);
            if (S.mask) S.mask = transformCanvas(S.mask, op);
            adjCache = cutCache = null;
            setAspect(S.aspect);
        }

        /* Textos */
        function textDown(e, t, d) {
            e.preventDefault(); e.stopPropagation();
            if (S.sel !== t) { S.sel = t; drawPanel(); drawOverlays(); return; }
            const r = wrap.getBoundingClientRect();
            const move = (ev) => {
                t.x = clamp((ev.clientX - r.left) / r.width, 0.02, 0.98);
                t.y = clamp((ev.clientY - r.top) / r.height, 0.02, 0.98);
                d.style.left = t.x * 100 + '%'; d.style.top = t.y * 100 + '%';
            };
            const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up);
        }

        /* Pincel y "tocar para quitar" sobre la foto */
        const toImage = (e) => {
            const r = cv.getBoundingClientRect(), v = view();
            return { x: v.x + ((e.clientX - r.left) / r.width) * v.w, y: v.y + ((e.clientY - r.top) / r.height) * v.h, k: v.w / r.width };
        };
        function ensureMask() {
            if (!S.mask) { S.mask = canvas(base.width, base.height); const c = S.mask.getContext('2d'); c.fillStyle = '#fff'; c.fillRect(0, 0, base.width, base.height); }
            if (S.bgMode === 'original') S.bgMode = 'color';
        }
        stage.addEventListener('pointermove', (e) => {
            if (S.tab !== 'fondo' || (S.tool !== 'erase' && S.tool !== 'restore')) { brushRing.style.display = 'none'; return; }
            const sr = stage.getBoundingClientRect();
            Object.assign(brushRing.style, { display: 'block', left: e.clientX - sr.left + 'px', top: e.clientY - sr.top + 'px', width: S.brush * 2 + 'px', height: S.brush * 2 + 'px' });
        });
        stage.addEventListener('pointerleave', () => { brushRing.style.display = 'none'; });
        cv.addEventListener('pointerdown', (e) => {
            if (S.tab !== 'fondo' || S.tool === 'none') return;
            e.preventDefault();
            pushUndo();
            ensureMask();
            if (S.tool === 'tap') {
                const p = toImage(e);
                regionFrom(base, S.mask, p.x, p.y, S.tol);
                cutCache = null; schedule(); drawPanel();
                return;
            }
            const ctx = S.mask.getContext('2d');
            ctx.globalCompositeOperation = S.tool === 'erase' ? 'destination-out' : 'source-over';
            ctx.fillStyle = '#fff'; ctx.strokeStyle = '#fff'; ctx.lineCap = ctx.lineJoin = 'round';
            let last = toImage(e);
            const dot = (p) => { ctx.beginPath(); ctx.arc(p.x, p.y, S.brush * p.k, 0, Math.PI * 2); ctx.fill(); };
            dot(last);
            cutCache = null; schedule();
            const move = (ev) => {
                const p = toImage(ev);
                ctx.lineWidth = S.brush * 2 * p.k;
                ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
                last = p; cutCache = null; schedule();
            };
            const up = () => { ctx.globalCompositeOperation = 'source-over'; window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); drawPanel(); };
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up);
        });

        async function busy(msg, fn) {
            const b = el('div', { class: 'rye-ed-busy' }, msg);
            stage.append(b);
            await new Promise((r) => setTimeout(r, 30));
            try { return fn(); } finally { b.remove(); }
        }

        /* Paneles */
        const slider = (label, key) => el('label', {}, [
            el('span', { text: label }),
            el('input', { type: 'range', min: -100, max: 100, value: S.adjust[key], oninput: (e) => { S.adjust[key] = +e.target.value; S.preset = ''; adjCache = cutCache = null; schedule(); } }),
        ]);
        const swatch = (color, active, onpick, title) => el('button', { type: 'button', class: 'rye-ed-sw' + (active ? ' on' : ''), title, style: `background:${color}`, onclick: onpick });

        function drawPanel() {
            const P = [];
            if (S.tab === 'recortar') {
                P.push(el('div', { class: 'rye-ed-row' }, ASPECTS.map(([l, a]) => el('button', { type: 'button', class: S.aspect === a ? 'on' : '', onclick: () => setAspect(a) }, l))));
                P.push(el('div', { class: 'rye-ed-row' }, [
                    el('button', { type: 'button', onclick: () => geometry('left') }, '⟲ Girar'),
                    el('button', { type: 'button', onclick: () => geometry('right') }, '⟳ Girar'),
                    el('button', { type: 'button', onclick: () => geometry('flip') }, '⇋ Voltear'),
                ]));
                P.push(el('p', { class: 'rye-ed-hint', text: 'Arrastra el recuadro o sus esquinas. Para Instagram/Facebook usa 1:1 o 4:5; para historias y TikTok, 9:16.' }));
            }
            if (S.tab === 'ajustes') {
                P.push(el('div', { class: 'rye-ed-row' }, [slider('Brillo', 'brightness'), slider('Contraste', 'contrast')]));
                P.push(el('div', { class: 'rye-ed-row' }, [slider('Saturación', 'saturation'), slider('Calidez', 'warmth')]));
                P.push(el('div', { class: 'rye-ed-row' }, el('button', { type: 'button', onclick: () => { S.adjust = { brightness: 0, contrast: 0, saturation: 0, warmth: 0, gray: false }; S.preset = 'Original'; adjCache = cutCache = null; drawPanel(); schedule(); } }, 'Restablecer')));
            }
            if (S.tab === 'filtros') {
                P.push(el('div', { class: 'rye-ed-row' }, PRESETS.map(([l, v]) => el('button', {
                    type: 'button', class: S.preset === l ? 'on' : '',
                    onclick: () => { S.adjust = { brightness: 0, contrast: 0, saturation: 0, warmth: 0, gray: false, ...v }; S.preset = l; adjCache = cutCache = null; drawPanel(); schedule(); },
                }, l))));
            }
            if (S.tab === 'fondo') {
                P.push(el('div', { class: 'rye-ed-row' }, [
                    el('button', { type: 'button', class: 'ok', onclick: () => busy('Quitando el fondo…', () => {
                        pushUndo();
                        const r = autoMask(base, S.tol);
                        S.mask = alphaToCanvas(r.alpha, base.width, base.height);
                        if (S.bgMode === 'original') S.bgMode = 'color';
                        cutCache = null; schedule(); drawPanel();
                        if (r.removedPct < 0.03) alert('No encontré un fondo liso para quitar. Prueba subir la "Sensibilidad" o usa "Tocar para quitar" y el pincel.');
                        else if (r.removedPct > 0.97) { undo(); alert('Se iba a borrar casi toda la foto. Baja la "Sensibilidad" y vuelve a intentar.'); }
                    }) }, '🪄 Quitar fondo'),
                    el('label', {}, [el('span', { text: 'Sensibilidad' }), el('input', { type: 'range', min: 0, max: 100, value: S.tol, oninput: (e) => { S.tol = +e.target.value; } })]),
                ]));
                P.push(el('p', { class: 'rye-ed-hint', text: 'Funciona mejor con el calzado sobre una superficie lisa (piso, mesa, pared). Después retoca con las herramientas de abajo.' }));
                P.push(el('div', { class: 'rye-ed-row' }, [
                    ['tap', '👆 Tocar para quitar'], ['erase', '🧽 Borrar con el dedo'], ['restore', '🖌️ Recuperar'],
                ].map(([k, l]) => el('button', { type: 'button', class: S.tool === k ? 'on' : '', onclick: () => { S.tool = S.tool === k ? 'none' : k; drawPanel(); } }, l))));
                if (S.tool === 'erase' || S.tool === 'restore') {
                    P.push(el('div', { class: 'rye-ed-row' }, el('label', {}, [el('span', { text: 'Tamaño' }), el('input', { type: 'range', min: 6, max: 80, value: S.brush, oninput: (e) => { S.brush = +e.target.value; } })])));
                }
                P.push(el('div', { class: 'rye-ed-row' }, [
                    el('span', { style: 'color:#94a3b8;width:92px', text: 'Fondo' }),
                    ...SWATCHES.map((c) => swatch(c, S.bgMode === 'color' && S.bgColor === c, () => { ensureMask(); S.bgMode = 'color'; S.bgColor = c; schedule(); drawPanel(); }, c)),
                    el('input', { type: 'color', value: S.bgColor, title: 'Otro color', oninput: (e) => { ensureMask(); S.bgMode = 'color'; S.bgColor = e.target.value; schedule(); } }),
                    el('button', { type: 'button', class: S.bgMode === 'transparent' ? 'on' : '', onclick: () => { ensureMask(); S.bgMode = 'transparent'; schedule(); drawPanel(); } }, 'Transparente'),
                    el('button', { type: 'button', class: S.bgMode === 'original' ? 'on' : '', onclick: () => { S.bgMode = 'original'; schedule(); drawPanel(); } }, 'Fondo original'),
                ]));
                P.push(el('div', { class: 'rye-ed-row' }, el('label', { style: 'flex:none' }, [
                    el('input', { type: 'checkbox', checked: S.shadow, onchange: (e) => { S.shadow = e.target.checked; schedule(); } }), el('span', { style: 'width:auto;color:#e2e8f0', text: 'Sombra suave debajo del calzado' }),
                ])));
            }
            if (S.tab === 'texto') {
                P.push(el('div', { class: 'rye-ed-row' }, [
                    el('button', { type: 'button', class: 'ok', onclick: () => { const t = { text: 'Bs 350', x: 0.5, y: 0.85, size: 0.09, color: '#ffffff', bg: '#dc2626', bold: true }; S.texts.push(t); S.sel = t; drawPanel(); drawOverlays(); } }, '+ Agregar texto'),
                    el('button', { type: 'button', onclick: () => { const t = { text: '¡NUEVO!', x: 0.5, y: 0.12, size: 0.08, color: '#111827', bg: '#facc15', bold: true }; S.texts.push(t); S.sel = t; drawPanel(); drawOverlays(); } }, '+ Etiqueta "¡NUEVO!"'),
                ]));
                const t = S.sel && S.texts.includes(S.sel) ? S.sel : null;
                if (t) {
                    P.push(el('textarea', { oninput: (e) => { t.text = e.target.value; drawOverlays(); } }, t.text));
                    P.push(el('div', { class: 'rye-ed-row' }, [
                        el('label', {}, [el('span', { text: 'Tamaño' }), el('input', { type: 'range', min: 3, max: 25, value: Math.round(t.size * 100), oninput: (e) => { t.size = e.target.value / 100; drawOverlays(); } })]),
                        el('button', { type: 'button', class: t.bold ? 'on' : '', onclick: () => { t.bold = !t.bold; drawPanel(); drawOverlays(); } }, 'Negrita'),
                    ]));
                    P.push(el('div', { class: 'rye-ed-row' }, [el('span', { style: 'color:#94a3b8;width:92px', text: 'Letra' }), ...TEXT_COLORS.map((c) => swatch(c, t.color === c, () => { t.color = c; drawPanel(); drawOverlays(); }, c)),
                        el('input', { type: 'color', value: t.color, oninput: (e) => { t.color = e.target.value; drawOverlays(); } })]));
                    P.push(el('div', { class: 'rye-ed-row' }, [el('span', { style: 'color:#94a3b8;width:92px', text: 'Fondo letra' }),
                        el('button', { type: 'button', class: !t.bg ? 'on' : '', onclick: () => { t.bg = null; drawPanel(); drawOverlays(); } }, 'Sin fondo'),
                        ...['#dc2626', '#facc15', '#111827', '#ffffff', '#16a34a', '#2563eb'].map((c) => swatch(c, t.bg === c, () => { t.bg = c; drawPanel(); drawOverlays(); }, c))]));
                    P.push(el('div', { class: 'rye-ed-row' }, el('button', { type: 'button', onclick: () => { S.texts = S.texts.filter((x) => x !== t); S.sel = null; drawPanel(); drawOverlays(); } }, '🗑 Quitar este texto')));
                    P.push(el('p', { class: 'rye-ed-hint', text: 'Arrastra el texto sobre la foto para moverlo. Toca otro texto para editarlo.' }));
                } else {
                    P.push(el('p', { class: 'rye-ed-hint', text: S.texts.length ? 'Toca un texto de la foto para editarlo.' : 'Agrega el precio, "¡Nuevo!", "Oferta" o lo que quieras.' }));
                }
            }
            panel.replaceChildren(...P);
        }

        /* Cerrar */
        function cleanup() {
            ro.disconnect();
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = prevOverflow;
            root.remove();
        }
        async function close(save) {
            if (!save) {
                const touched = S.undo.length || S.texts.length || S.preset !== 'Original' || S.crop.w !== base.width || S.crop.h !== base.height;
                if (touched && !confirm('¿Salir sin guardar los cambios de la foto?')) return;
                cleanup();
                opts.onCancel && opts.onCancel();
                return;
            }
            const W = Math.round(S.crop.w), H = Math.round(S.crop.h);
            const out = canvas(W, H);
            compose(out.getContext('2d'), W, H, S.crop.x, S.crop.y, S.crop.w, S.crop.h, true);
            const png = S.bgMode === 'transparent' && !!S.mask;
            const blob = await new Promise((r) => out.toBlob(r, png ? 'image/png' : 'image/jpeg', 0.9));
            cleanup();
            if (!blob) { alert('No se pudo guardar la foto editada. Intenta de nuevo.'); return; }
            opts.onDone && opts.onDone(blob, name + (png ? '.png' : '.jpg'));
        }
        const onKey = (e) => {
            if (e.key === 'Escape') close(false);
            if ((e.ctrlKey || e.metaKey) && e.key === 'z') { e.preventDefault(); undo(); }
        };
        document.addEventListener('keydown', onKey);

        drawTabs(); drawPanel(); schedule();
    }

    window.RyEPhotoEditor = { open, _autoMask: autoMask, _applyAdjust: applyAdjust };
    // Piezas compartidas con el editor de video
    window.RyEEditorKit = { el, canvas, clamp, injectCss, drawTexts, roundRect, FONT, TEXT_COLORS };
})();
