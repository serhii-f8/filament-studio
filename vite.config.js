import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

// Builds the flow designer canvas into resources/dist so host apps need no JS toolchain:
// the files are registered as Filament assets and published by `php artisan filament:assets`.
export default defineConfig({
    plugins: [react(), tailwindcss()],
    build: {
        outDir: 'resources/dist',
        emptyOutDir: true,
        cssCodeSplit: false,
        modulePreload: false,
        rollupOptions: {
            input: 'resources/js/flows/entry.ts',
            output: {
                format: 'es',
                inlineDynamicImports: true,
                entryFileNames: 'flow-canvas.js',
                assetFileNames: 'flow-canvas[extname]',
            },
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js/flows/__tests__/setup.ts'],
        include: ['resources/js/**/__tests__/**/*.{test,spec}.{ts,tsx}'],
    },
});
