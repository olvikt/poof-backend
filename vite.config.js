import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    // Defense-in-depth: keep Vite dev server local-only to avoid network-reachable
    // exposure for known dev-server advisories unless explicitly opted in.
    server: {
        host: '127.0.0.1',
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/auth.js',
                'resources/js/poof/order-create.js',
            ],
            refresh: true,
        }),
    ],
    build: {
        target: 'esnext',
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['axios'],
                    alpine: ['alpinejs'],
                },
            },
        },
    },
});
