<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'Laravel'))</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Livewire Styles -->
    @livewireStyles
    
    <!-- Tailwind CSS CDN como fallback -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
</head>

<body class="bg-gray-50 antialiased">
    <div id="app">
        @yield('content', $slot ?? '')
    </div>

    <!-- Livewire Scripts -->
    @livewireScripts
    
    <!-- Alpine.js para interactividad adicional -->
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    
    <!-- Scripts personalizados -->
    <script>
        // Configuración global para Livewire
        document.addEventListener('livewire:init', () => {
            // Auto-scroll a elementos con errores
            Livewire.on('validation-error', (event) => {
                setTimeout(() => {
                    const firstError = document.querySelector('.error');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }, 100);
            });
            
            // Notificaciones de éxito/error
            Livewire.on('notify', (event) => {
                const type = event.type || 'info';
                const message = event.message || '';
                
                // Aquí puedes integrar tu sistema de notificaciones preferido
                if (type === 'success') {
                    console.log('✅ ' + message);
                } else if (type === 'error') {
                    console.log('❌ ' + message);
                }
            });
        });
        
        // Función para manejar atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S para guardar
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const saveButton = document.querySelector('[wire\\:click="generatePdf"]');
                if (saveButton && !saveButton.disabled) {
                    saveButton.click();
                }
            }
            
            // Escape para cerrar modales
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal.active');
                if (activeModal) {
                    // Cerrar modal si existe
                    const closeButton = activeModal.querySelector('.modal-close');
                    if (closeButton) {
                        closeButton.click();
                    }
                }
            }
        });
        
        // Función para mejorar la accesibilidad
        function enhanceAccessibility() {
            // Agregar roles ARIA donde sea necesario
            const editableFields = document.querySelectorAll('.editable-field');
            editableFields.forEach(field => {
                if (!field.getAttribute('role')) {
                    field.setAttribute('role', 'textbox');
                }
                if (!field.getAttribute('aria-label')) {
                    const label = field.closest('td')?.querySelector('.bold')?.textContent;
                    if (label) {
                        field.setAttribute('aria-label', label.replace(':', ''));
                    }
                }
            });
        }
        
        // Ejecutar mejoras de accesibilidad cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', enhanceAccessibility);
        
        // Re-ejecutar después de actualizaciones de Livewire
        document.addEventListener('livewire:navigated', enhanceAccessibility);
        
        // Función para mejorar la experiencia de usuario en dispositivos táctiles
        function enhanceTouchExperience() {
            if ('ontouchstart' in window) {
                document.body.classList.add('touch-device');
                
                // Aumentar el área de toque para campos pequeños
                const style = document.createElement('style');
                style.textContent = `
                    .touch-device .editable-field {
                        min-height: 44px;
                        padding: 8px;
                    }
                    
                    .touch-device .checkbox-custom {
                        min-width: 44px;
                        min-height: 44px;
                        line-height: 42px;
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        enhanceTouchExperience();
    </script>
</body>
</html>
