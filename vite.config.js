import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',                    // dein globales CSS
                'resources/js/app.js',                      // optional
                'resources/css/filament/admin/theme.css',   // <— NEU: dein Filament-Theme
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
