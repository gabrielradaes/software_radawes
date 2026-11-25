/*
 * Fotos y videos de un formulario (publicaciones, productos):
 * - elegir o arrastrar archivos, ver miniaturas, ordenar, quitar
 * - ✏️ editar fotos (RyEPhotoEditor) y videos (RyEVideoEditor), o crear un video desde cero
 * - achica fotos pesadas antes de subir, crea la portada de los videos
 * - envía el formulario con barra de progreso y muestra los errores sin perder lo elegido
 *
 * Uso (Alpine): x-data="ryeMediaUploader({ images: 'media[]', videos: true, maxVideoMb: 90 })"
 */
(function () {
    'use strict';
    const IMG_MAX_SIDE = 2000;
    let uid = 0;

    function isHeic(f) { return /heic|heif/i.test(f.type) || /\.(heic|heif)$/i.test(f.name); }
    const mb = (b) => (b / 1048576).toFixed(1).replace('.0', '') + ' MB';

    function loadImg(blob) {
        return new Promise((res, rej) => { const u = URL.createObjectURL(blob); const i = new Image(); i.onload = () => res({ i, u }); i.onerror = () => { URL.revokeObjectURL(u); rej(new Error('img')); }; i.src = u; });
    }
    async function shrink(file) {
        const { i, u } = await loadImg(file);
        URL.revokeObjectURL(u);
        const k = Math.min(1, IMG_MAX_SIDE / Math.max(i.naturalWidth, i.naturalHeight));
        const c = document.createElement('canvas');
        c.width = Math.round(i.naturalWidth * k); c.height = Math.round(i.naturalHeight * k);
        c.getContext('2d').drawImage(i, 0, 0, c.width, c.height);
        const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', 0.88));
        if (!blob) throw new Error('img');
        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
    }
    function posterOf(file) {
        return new Promise((resolve) => {
            const u = URL.createObjectURL(file);
            const v = document.createElement('video');
            v.muted = true; v.playsInline = true; v.preload = 'auto'; v.src = u;
            const fail = () => { URL.revokeObjectURL(u); resolve(null); };
            const timer = setTimeout(fail, 8000);
            v.onerror = () => { clearTimeout(timer); fail(); };
            v.onloadedmetadata = () => { if (!v.videoWidth) { clearTimeout(timer); fail(); return; } v.currentTime = Math.min(0.5, (v.duration || 1) / 2); };
            v.onseeked = () => {
                clearTimeout(timer);
                const k = Math.min(1, 1080 / Math.max(v.videoWidth, v.videoHeight));
                const c = document.createElement('canvas'); c.width = v.videoWidth * k; c.height = v.videoHeight * k;
                c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
                c.toBlob((b) => { URL.revokeObjectURL(u); resolve(b); }, 'image/jpeg', 0.85);
            };
        });
    }

    window.ryeMediaUploader = function (opts = {}) {
        return {
            items: [],
            dragging: false,
            uploading: false,
            progress: 0,
            errors: [],
            maxVideoMb: opts.maxVideoMb || 90,
            withVideos: !!opts.videos,
            imageField: opts.images || 'media[]',
            canEditVideo: !!(window.RyEVideoEditor && window.RyEVideoEditor.supported()),

            init() {
                const form = this.$root.closest('form');
                if (!form) return;
                this.form = form;
                form.addEventListener('submit', (e) => this.submit(e));
                // Avisa al formulario cuál es la primera foto (para la vista previa)
                this.$watch('items', () => {
                    const first = this.items.find((i) => i.kind === 'image') || this.items.find((i) => i.posterUrl);
                    this.$dispatch('media-changed', { image: first ? (first.kind === 'image' ? first.url : first.posterUrl) : null, count: this.items.length });
                });
            },

            async add(list) {
                this.errors = [];
                for (const f of [...list]) {
                    if (f.type.startsWith('video/') || /\.(mp4|mov|webm|m4v)$/i.test(f.name)) {
                        if (!this.withVideos) { this.errors.push('Aquí solo se pueden subir fotos.'); continue; }
                        if (f.size > this.maxVideoMb * 1048576) {
                            this.errors.push('«' + f.name + '» pesa ' + mb(f.size) + ' (máximo ' + this.maxVideoMb + ' MB).' + (this.canEditVideo ? ' Ábrelo en el editor de video y expórtalo más corto: queda más liviano.' : ''));
                            if (this.canEditVideo && confirm('El video pesa ' + mb(f.size) + ' y el máximo es ' + this.maxVideoMb + ' MB.\n¿Abrirlo en el editor para recortarlo y achicarlo?')) this.openVideoEditor([f]);
                            continue;
                        }
                        const item = { id: ++uid, kind: 'video', file: f, name: f.name, url: URL.createObjectURL(f), poster: null, posterUrl: null, edited: false, size: f.size, checking: true };
                        this.items.push(item);
                        posterOf(f).then((b) => {
                            const it = this.items.find((x) => x.id === item.id);
                            if (!it) return;
                            it.checking = false;
                            if (b) { it.poster = b; it.posterUrl = URL.createObjectURL(b); } else {
                                it.warning = 'Este navegador no puede reproducir este video: puede que tus clientes tampoco. Grábalo en formato "Más compatible" o súbelo a Facebook/TikTok y pega el enlace.';
                            }
                        });
                        continue;
                    }
                    if (!f.type.startsWith('image/') && !isHeic(f)) { this.errors.push('«' + f.name + '» no es una foto ni un video.'); continue; }
                    let file = f;
                    try {
                        if (isHeic(f) || f.size > 1.5 * 1048576 || !/jpe?g|png|webp/i.test(f.type)) file = await shrink(f);
                    } catch (e) {
                        this.errors.push(isHeic(f)
                            ? '«' + f.name + '» es una foto HEIC de iPhone y este navegador no la abre. En el iPhone: Ajustes › Cámara › Formatos › "Más compatible".'
                            : '«' + f.name + '» no se pudo leer. Prueba con otra foto.');
                        continue;
                    }
                    this.items.push({ id: ++uid, kind: 'image', file, name: file.name, url: URL.createObjectURL(file), edited: false, size: file.size });
                }
            },
            pick(kind) { this.$refs[kind === 'video' ? 'videoIn' : 'imageIn'].click(); },
            drop(e) { this.dragging = false; if (e.dataTransfer && e.dataTransfer.files.length) this.add(e.dataTransfer.files); },
            remove(it) { URL.revokeObjectURL(it.url); this.items = this.items.filter((x) => x !== it); },
            move(it, d) { const i = this.items.indexOf(it), j = i + d; if (j < 0 || j >= this.items.length) return; const a = [...this.items]; [a[i], a[j]] = [a[j], a[i]]; this.items = a; },

            edit(it) {
                if (it.kind === 'image') {
                    window.RyEPhotoEditor.open(it.file, {
                        onDone: (blob, name) => {
                            const f = new File([blob], name, { type: blob.type });
                            URL.revokeObjectURL(it.url);
                            Object.assign(it, { file: f, name, url: URL.createObjectURL(f), edited: true, size: f.size });
                            this.items = [...this.items];
                        },
                    });
                } else {
                    this.openVideoEditor([it.file], it);
                }
            },
            openVideoEditor(files, replace) {
                window.RyEVideoEditor.open(files, {
                    maxMb: this.maxVideoMb,
                    onDone: (blob, poster, name) => {
                        const f = new File([blob], name, { type: blob.type });
                        const item = { id: ++uid, kind: 'video', file: f, name, url: URL.createObjectURL(f), poster, posterUrl: poster ? URL.createObjectURL(poster) : null, edited: true, size: f.size };
                        if (replace && this.items.includes(replace)) { this.items.splice(this.items.indexOf(replace), 1, item); this.items = [...this.items]; } else { this.items.push(item); }
                    },
                });
            },

            totalSize() { return this.items.reduce((s, i) => s + i.size + (i.poster ? i.poster.size : 0), 0); },

            submit(e) {
                if (this.uploading) { e.preventDefault(); return; }
                // Sin archivos nuevos: el formulario se envía normal
                if (!this.items.length) return;
                e.preventDefault();
                if (this.items.some((i) => i.checking)) { alert('Espera un segundo: estamos preparando el video.'); return; }
                const fd = new FormData(this.form);
                let v = 0;
                for (const it of this.items) {
                    if (it.kind === 'image') fd.append(this.imageField, it.file, it.name);
                    else {
                        fd.append('videos[' + v + ']', it.file, it.name);
                        if (it.poster) fd.append('video_posters[' + v + ']', it.poster, 'portada-' + v + '.jpg');
                        v++;
                    }
                }
                this.send(fd);
            },
            send(fd) {
                this.uploading = true; this.progress = 0; this.errors = [];
                const xhr = new XMLHttpRequest();
                xhr.open('POST', this.form.action);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-RyE-Upload', '1');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.upload.onprogress = (ev) => { if (ev.lengthComputable) this.progress = Math.round((ev.loaded / ev.total) * 100); };
                xhr.onload = () => {
                    let data = null;
                    try { data = JSON.parse(xhr.responseText); } catch (e) { /* no era JSON */ }
                    if (xhr.status >= 200 && xhr.status < 300 && data && data.redirect) { window.location.href = data.redirect; return; }
                    this.uploading = false;
                    if (xhr.status === 422 && data && data.errors) { this.errors = Object.values(data.errors).flat(); }
                    else if (xhr.status === 413) { this.errors = ['Los archivos pesan demasiado para el servidor (' + mb(this.totalSize()) + '). Sube menos o haz el video más corto.']; }
                    else if (xhr.status === 419) { this.errors = ['La página estuvo abierta mucho tiempo y la sesión venció. Copia tu texto, recarga la página (F5) y vuelve a intentar.']; }
                    else if (xhr.status === 403) { this.errors = [(data && data.message) || 'No tienes permiso para hacer esto.']; }
                    else { this.errors = ['No se pudo guardar (error ' + xhr.status + '). Revisa tu internet e intenta de nuevo; tus fotos y videos siguen aquí.']; }
                    this.$nextTick(() => this.$root.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                };
                xhr.onerror = () => { this.uploading = false; this.errors = ['Se cortó la conexión mientras subía. Revisa tu internet e intenta de nuevo: tus fotos y videos siguen aquí.']; };
                xhr.send(fd);
            },
        };
    };
})();
