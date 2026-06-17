import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Public marketing site (Blade)
                'resources/css/app.css',
                'resources/js/app.js',
                // Admin panel (Vue SPA)
                'resources/js/admin/main.js',
            ],
            refresh: true,
            fonts: [
                // Brand typefaces (Kheedma GSM): Syncopate display, Montserrat body.
                bunny('Syncopate', { weights: [400, 700] }),
                bunny('Montserrat', { weights: [400, 500, 600, 700] }),
            ],
        }),
        tailwindcss(),
        vue(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
