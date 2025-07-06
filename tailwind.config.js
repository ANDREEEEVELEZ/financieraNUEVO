import preset from './vendor/filament/filament/tailwind.config.preset'

export default {
    presets: [preset],
    corePlugins: {
        textColor: true,
    },
    content: [
        './app/Filament/Dashboard//*.php',
        './resources/views/filament/dashboard//*.blade.php',
        './resources/css/filament/dashboard//*.css',
        './vendor/filament//*.blade.php',
    ],
    safelist: [
        'fi-sidebar',
        'fi-sidebar-group',
        'fi-sidebar-item-icon',
        'fi-sidebar-item',
        'fi-sidebar-item-label',
        'fi-sidebar-item-active',
        'fi-main-ctn',
        'fi-header',
        'fi-button',
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