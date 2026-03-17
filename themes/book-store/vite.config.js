import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['assets/src/js/app.js'],
            publicDirectory: 'assets',
            buildDirectory: 'dist',
            refresh: ['**/*.htm'],
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: process.env.VITE_PORT || 5173,
        cors: true,
        hmr: {
            host: process.env.VITE_HMR_HOST || 'localhost',
        },
        watch: {
            usePolling: true,
            interval: 100,
            ignored: ['**/node_modules/**', '**/storage/**']
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern-compiler'
            },
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'assets/src/js')
        },
    },
});
