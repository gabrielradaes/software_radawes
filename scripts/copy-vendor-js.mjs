// Copia Alpine.js a public/js para no depender de un CDN en producción,
// y los editores de fotos/video (resources/js/editor → public/js/editor).
import { copyFileSync, mkdirSync, readdirSync } from 'node:fs';
mkdirSync('public/js/editor', { recursive: true });
copyFileSync('node_modules/alpinejs/dist/cdn.min.js', 'public/js/alpine.min.js');
copyFileSync('node_modules/@alpinejs/collapse/dist/cdn.min.js', 'public/js/alpine-collapse.min.js');
for (const f of readdirSync('resources/js/editor').filter((n) => n.endsWith('.js'))) {
    copyFileSync(`resources/js/editor/${f}`, `public/js/editor/${f}`);
}
console.log('Alpine y editores copiados a public/js');
