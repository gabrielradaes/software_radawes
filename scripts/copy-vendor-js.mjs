// Copia Alpine.js a public/js para no depender de un CDN en producción.
import { copyFileSync, mkdirSync } from 'node:fs';
mkdirSync('public/js', { recursive: true });
copyFileSync('node_modules/alpinejs/dist/cdn.min.js', 'public/js/alpine.min.js');
copyFileSync('node_modules/@alpinejs/collapse/dist/cdn.min.js', 'public/js/alpine-collapse.min.js');
console.log('Alpine copiado a public/js');
