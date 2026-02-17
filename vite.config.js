import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/dashboard/theme.css'
            ],
            refresh: true,
        }),
    ],

    // Optimizaciones de build para producción
    build: {
        // Usar terser para minificación más agresiva
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,      // Elimina console.log en producción
                drop_debugger: true,     // Elimina debugger statements
                pure_funcs: ['console.log', 'console.info', 'console.debug'],
            },
            mangle: {
                safari10: true,          // Compatibilidad Safari 10
            },
            format: {
                comments: false,         // Elimina comentarios
            },
        },

        // Code splitting para mejor caching
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor': ['axios'],
                },
                // Nombres de archivo con hash para cache-busting
                chunkFileNames: 'assets/js/[name]-[hash].js',
                entryFileNames: 'assets/js/[name]-[hash].js',
                assetFileNames: 'assets/[ext]/[name]-[hash].[ext]',
            },
        },

        // Inline assets pequeños (menos de 4KB)
        assetsInlineLimit: 4096,

        // Generar sourcemaps solo en desarrollo
        sourcemap: false,

        // Target browsers modernos para bundles más pequeños
        target: 'es2020',

        // Tamaño máximo de chunk (500KB warning)
        chunkSizeWarningLimit: 500,
    },

    // Optimización de CSS
    css: {
        devSourcemap: true,
    },

    // Optimizar dependencias pre-bundling
    optimizeDeps: {
        include: ['axios'],
    },

    // Configuración del servidor de desarrollo
    server: {
        hmr: {
            overlay: true,
        },
    },
});