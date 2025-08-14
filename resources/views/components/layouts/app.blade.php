<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Laravel') }}</title>

    <!-- Filament Styles -->
    @filamentStyles
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Livewire Styles -->
    @livewireStyles
    
    <!-- Tailwind CSS CDN como fallback -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Estilos personalizados para Filament Forms */
        .fi-section {
            margin-bottom: 1.5rem;
        }
        
        .fi-section-header {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .fi-section-header h3 {
            color: #1f2937;
            font-weight: 600;
            font-size: 1.125rem;
        }
        
        .fi-section-header p {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        /* Estilos para campos deshabilitados */
        .fi-input[disabled],
        .fi-select[disabled] {
            background-color: #f9fafb !important;
            border-color: #d1d5db !important;
            color: #6b7280 !important;
        }
        
        /* Mejorar el espaciado de los checkboxes */
        .fi-checkbox {
            margin-bottom: 0.5rem;
        }
        
        /* Estilo para el formulario en dispositivos móviles */
        @media (max-width: 768px) {
            .fi-grid-cols-2 {
                grid-template-columns: 1fr !important;
            }
        }
        
        /* Personalización adicional para la declaración jurada */
        .declaracion-form {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body class="bg-gray-50 antialiased">
    <div id="app" class="min-h-screen">
        {{ $slot }}
    </div>

    <!-- Filament Scripts -->
    @filamentScripts
    
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
                const saveButton = document.querySelector('[wire\\:click="generarPDF"]');
                if (saveButton && !saveButton.disabled) {
                    saveButton.click();
                }
            }
            
            // Escape para volver
            if (e.key === 'Escape') {
                const backButton = document.querySelector('[wire\\:click="cancelar"]');
                if (backButton) {
                    backButton.click();
                }
            }
        });
        
        // Función para mejorar la accesibilidad
        function enhanceAccessibility() {
            const editableFields = document.querySelectorAll('.fi-input, .fi-select, .fi-textarea');
            editableFields.forEach(field => {
                if (!field.getAttribute('role')) {
                    field.setAttribute('role', 'textbox');
                }
            });
        }
        
        // Ejecutar mejoras de accesibilidad cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', enhanceAccessibility);
        
        // Re-ejecutar después de actualizaciones de Livewire
        document.addEventListener('livewire:navigated', enhanceAccessibility);
    </script>
</body>
</html>
