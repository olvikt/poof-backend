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
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) {
                        return;
                    }

                    if (id.includes('/alpinejs/')) {
                        return 'alpine';
                    }

                    if (id.includes('/axios/')) {
                        return 'app';
                    }

                    return 'vendor';
                },
            },
        },
    },
});
