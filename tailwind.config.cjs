/**
 * Tailwind compilado (sin CDN). Los colores de marca son variables CSS:
 * cada tienda define sus valores en <style>:root{...}</style> (ver partials/theme-vars),
 * así una sola hoja de estilos sirve para las 3 tiendas y para el panel.
 */
const v = (name) => `rgb(var(--${name}) / <alpha-value>)`;

module.exports = {
    content: [
        './resources/views/**/*.blade.php',
        './app/**/*.php',
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'system-ui', 'sans-serif'],
                display: ['"Playfair Display"', 'Georgia', 'serif'],
            },
            colors: {
                brand: {
                    50: v('brand-50'), 100: v('brand-100'), 200: v('brand-200'), 300: v('brand-300'),
                    500: v('brand-500'), 700: v('brand-700'), 800: v('brand-800'), 900: v('brand-900'), 950: v('brand-950'),
                },
                gold: { 400: v('gold-400'), 500: v('gold-500'), 600: v('gold-600') },
                wa: { 500: '#25D366', 600: '#1ebe5b' },
            },
        },
    },
    plugins: [],
};
