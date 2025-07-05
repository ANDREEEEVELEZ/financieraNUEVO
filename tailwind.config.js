import preset from './vendor/filament/filament/tailwind.config.preset'

export default {
    presets: [preset],
    corePlugins: {
        textColor: true,
    },
    content: [
        './app/Filament/Dashboard//*.php',
        './resources/views/filament/dashboard//*.blade.php',
        './resources/css/filament/dashboard//*.css', // ✅ <- AGREGAR ESTO
        './vendor/filament//*.blade.php',
    ],
    safelist: [
        'text-white',
    ],
    theme: {
        extend: {
            colors: {
                white: '#ffffff',
            },
        }
    }
}