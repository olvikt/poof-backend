import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/poof/order-create.js',
            ],
            refresh: true,
        }),
    ],
    build: {
        target: 'esnext',
        rollupOptions: {
            external: ['/vendor/livewire/livewire/dist/livewire.esm'],
            output: {
                manualChunks: {
                    vendor: ['axios'],
                    alpine: ['alpinejs'],
                },
            },
        },
    },
});
