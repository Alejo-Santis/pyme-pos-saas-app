import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        svelte(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        host: '0.0.0.0', // necesario para Docker
        port: 5173,
        // Sin esto, el navegador bloquea los <script type="module"> de Vite por CORS
        // cuando la página se sirve desde un subdominio de tenant (ej. empresa.lvh.me:8000)
        // en vez de localhost:8000 — origen distinto al de este dev server (localhost:5173).
        cors: true,
        hmr: {
            host: 'localhost',
        },
        watch: {
            // vendor/ y storage/ nunca cambian por HMR y suman miles de archivos —
            // vigilarlos agota el límite de inotify del sistema (ENOSPC) en proyectos
            // Laravel grandes. node_modules ya lo ignora chokidar por defecto.
            ignored: ['**/vendor/**', '**/storage/**', '**/.git/**'],
        },
    },
});
