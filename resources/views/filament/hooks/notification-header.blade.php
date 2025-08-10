<div class="flex items-center space-x-4">
    <div class="relative" x-data="{ 
        open: false, 
        notifications: [], 
        unreadCount: 0,
        loading: false,
        
        async init() {
            console.log('Inicializando componente de notificaciones...');
            await this.loadNotifications();
            // Refrescar cada 30 segundos
            setInterval(() => {
                console.log('Auto-refresh de notificaciones...');
                this.loadNotifications();
            }, 30000);
        },
        
        async loadNotifications() {
            this.loading = true;
            try {
                // Obtener token CSRF
                const csrfToken = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') 
                    || document.querySelector('input[name="_token"]')?.value;
                
                if (!csrfToken) {
                    console.warn('Token CSRF no encontrado, intentando sin él...');
                }

                const headers = {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                };

                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken;
                }
                
                console.log('Cargando notificaciones desde: /api/notifications');
                
                const response = await fetch('/api/notifications', {
                    method: 'GET',
                    headers: headers,
                    credentials: 'same-origin'
                });
                
                console.log('Respuesta recibida:', response.status, response.statusText);
                
                if (response.ok) {
                    const data = await response.json();
                    this.notifications = data.notifications || [];
                    this.unreadCount = data.unreadCount || 0;
                    console.log('Notificaciones cargadas:', this.notifications.length);
                    
                    if (data.debug) {
                        console.log('Debug info:', data.debug);
                    }
                } else {
                    const errorData = await response.json().catch(() => ({}));
                    console.error('Error al cargar notificaciones:', {
                        status: response.status,
                        statusText: response.statusText,
                        error: errorData
                    });
                    
                    // Mostrar mensaje de error al usuario si es necesario
                    if (response.status === 401) {
                        console.warn('Usuario no autenticado, redirigiendo...');
                        // window.location.href = '/login';
                    }
                }
            } catch (error) {
                console.error('Error de red o JavaScript:', error);
            } finally {
                this.loading = false;
            }
        },
        
        toggleDropdown() {
            this.open = !this.open;
            console.log('Dropdown toggled:', this.open);
        },
        
        redirectTo(url) {
            this.open = false;
            window.location.href = url;
        },
        
        // Método temporal para debug
        async testNotifications() {
            console.log('=== TEST MANUAL DE NOTIFICACIONES ===');
            
            // Test 1: API debug endpoint
            try {
                const debugResponse = await fetch('/api/notifications-debug', {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin'
                });
                
                console.log('Debug endpoint response:', debugResponse.status);
                if (debugResponse.ok) {
                    const debugData = await debugResponse.json();
                    console.log('Debug data:', debugData);
                }
            } catch (e) {
                console.error('Error en debug endpoint:', e);
            }
            
            // Test 2: Recargar notificaciones normales
            await this.loadNotifications();
        }
    }" x-init="init()">
        
        <!-- Botón de notificaciones -->
        <button 
            @click="toggleDropdown()"
            class="relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 rounded-lg transition-colors duration-200"
            type="button"
        >
            <!-- Icono de campana -->
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            
            <!-- Badge de contador -->
            <span 
                x-show="unreadCount > 0"
                x-text="unreadCount > 99 ? '99+' : unreadCount"
                class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold animate-pulse"
            ></span>
        </button>

        <!-- Dropdown de notificaciones -->
        <div 
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.away="open = false"
            class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
            style="z-index: 9999; max-height: 80vh; display: none;"
        >
            <!-- Header del dropdown -->
            <div class="px-4 py-3 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Notificaciones</h3>
                    <div class="flex items-center space-x-2">
                        <span x-show="loading" class="text-xs text-gray-500">Cargando...</span>
                        <!-- Botón de debug temporal -->
                        <button 
                            @click="testNotifications()"
                            class="text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600"
                            type="button"
                            title="Test de notificaciones (temporal)"
                        >
                            🔧 Debug
                        </button>
                    </div>
                </div>
            </div>

            <!-- Lista de notificaciones -->
            <div class="max-h-96 overflow-y-auto">
                <template x-for="notification in notifications" :key="notification.id">
                    <div 
                        @click="redirectTo(notification.url)"
                        class="px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 transition-colors duration-150"
                    >
                        <div class="flex items-start space-x-3">
                            <!-- Icono de la notificación -->
                            <div class="flex-shrink-0">
                                <span class="text-lg" x-text="notification.icon || '🔔'"></span>
                            </div>
                            
                            <!-- Contenido de la notificación -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate" x-text="notification.title"></p>
                                <p class="text-sm text-gray-500 mt-1" x-text="notification.description"></p>
                                <p class="text-xs text-gray-400 mt-1" x-text="notification.time"></p>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Estado vacío -->
                <div x-show="notifications.length === 0 && !loading" class="px-4 py-8 text-center">
                    <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <p class="mt-2 text-sm text-gray-500">No tienes notificaciones</p>
                </div>
            </div>
        </div>
    </div>
</div>
