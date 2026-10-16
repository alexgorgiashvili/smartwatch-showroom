import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [laravel({
        input: ['resources/js/gift-builder.js', 'resources/js/gift-box-experience.js'],
        buildDirectory: 'build/gift-v2',
        refresh: false,
    })],
    build: { chunkSizeWarningLimit: 1000 },
});
