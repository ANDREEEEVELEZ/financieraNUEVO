# Sistema de Notificaciones - Documentación

## 📋 **OVERVIEW**

El sistema de notificaciones permite mostrar alertas en tiempo real a los usuarios según su rol, sin crear nuevas tablas en la base de datos. Utiliza las columnas de estado existentes para generar notificaciones dinámicas.

## 🔧 **ARQUITECTURA**

### **Componentes Principales:**

1. **NotificationService** (`app/Services/NotificationService.php`)
   - Servicio principal que consulta la BD en tiempo real
   - Genera notificaciones según el rol del usuario
   - No requiere nuevas tablas

2. **API Endpoint** (`routes/api.php`)
   - Ruta: `/api/notifications`
   - Retorna JSON con notificaciones para el usuario actual
   - Requiere autenticación

3. **Componente Alpine.js** (`resources/views/filament/hooks/notification-header.blade.php`)
   - Interfaz visual en el header de Filament
   - Dropdown con lista de notificaciones
   - Auto-refresh cada 30 segundos

## 📊 **TIPOS DE NOTIFICACIONES**

### **Para Asesores:**
- ✅ **Préstamos Aprobados**: Cuando un préstamo que registró fue aprobado
- ❌ **Préstamos Rechazados**: Cuando un préstamo que registró fue rechazado

### **Para Supervisores (Jefe de Operaciones, Jefe de Créditos, Super Admin):**
- ⏳ **Préstamos Pendientes**: Préstamos en estado "Pendiente" que requieren aprobación
- 💰 **Pagos Pendientes**: Pagos en estado "pendiente" que requieren validación
- ⚠️ **Moras Pendientes**: Moras en estado "pendiente" que requieren gestión

## 🎯 **NAVEGACIÓN INTELIGENTE**

Cada notificación incluye una URL específica que lleva al usuario directamente donde puede tomar la acción:

| Tipo de Notificación | URL de Destino | Acción Disponible |
|---------------------|---------------|-------------------|
| Préstamo Pendiente | `/dashboard/prestamos/{id}/edit` | Aprobar/Rechazar |
| Pago Pendiente | `/dashboard/pagos/{id}/edit` | Validar pago |
| Mora Pendiente | `/dashboard/grupos/{id}` | Ver detalles de mora |
| Préstamo Respuesta | `/dashboard/prestamos/{id}` | Ver estado actual |

## 🔄 **FLUJO DE FUNCIONAMIENTO**

### **1. Carga Inicial:**
```javascript
// Al cargar la página
notificationComponent.init()
  ↓
loadNotifications()
  ↓
fetch('/api/notifications')
  ↓
NotificationService->getNotifications()
  ↓
Consultas a BD basadas en rol del usuario
```

### **2. Refresh Automático:**
```javascript
// Cada 30 segundos
setInterval(() => {
    loadNotifications();
}, 30000);
```

### **3. Click en Notificación:**
```javascript
// Al hacer click
redirectToNotification(notification)
  ↓
window.location.href = notification.url
  ↓
Usuario llega a la página específica para tomar acción
```

## 💾 **CONSULTAS A LA BASE DE DATOS**

### **Sin Nuevas Tablas:**
El sistema utiliza las tablas y columnas existentes:

- `prestamos.estado` = 'Pendiente' → Notificación a supervisores
- `prestamos.estado` = 'Aprobado'/'Rechazado' → Notificación a asesor
- `pagos.estado_pago` = 'pendiente' → Notificación a supervisores  
- `moras.estado_mora` = 'pendiente' → Notificación a supervisores

### **Consultas Optimizadas:**
```php
// Préstamos pendientes
Prestamo::where('estado', 'Pendiente')->get()

// Pagos pendientes
Pago::where('estado_pago', 'pendiente')->get()

// Moras pendientes
Mora::where('estado_mora', 'pendiente')
    ->with(['cuotaGrupal.prestamo.grupo'])
    ->get()
```

## 🎨 **INTERFAZ VISUAL**

### **Componentes UI:**
- 🔔 **Icono de campana** en el header (junto al perfil)
- 🔴 **Badge rojo** con contador de notificaciones no leídas
- 📋 **Dropdown** con lista detallada
- 🎯 **Click directo** para navegación

### **Estados Visuales:**
- ✅ Verde: Aprobaciones exitosas
- ⚠️ Amarillo: Pendientes de acción
- ❌ Rojo: Rechazos o moras
- 💙 Azul: Información general

## 🔧 **INSTALACIÓN Y CONFIGURACIÓN**

### **Archivos Creados:**
```
app/Services/NotificationService.php
routes/api.php
resources/views/filament/hooks/notification-header.blade.php
```

### **Modificaciones:**
```
app/Providers/AppServiceProvider.php (registro del servicio)
app/Providers/Filament/DashboardPanelProvider.php (hook del header)
bootstrap/app.php (rutas API)
```

### **No Requiere:**
- ❌ Nuevas migraciones
- ❌ Nuevas tablas
- ❌ Cambios en BD existente
- ❌ Instalación de paquetes adicionales

## 🚀 **VENTAJAS DEL SISTEMA**

### **Técnicas:**
- ✅ **Sin BD**: Usa tablas existentes
- ✅ **Tiempo Real**: Refresh cada 30 segundos
- ✅ **Performante**: Consultas optimizadas con relaciones
- ✅ **Escalable**: Fácil agregar nuevos tipos
- ✅ **Integrado**: Nativo en Filament

### **UX/UI:**
- ✅ **Intuitivo**: Click directo a la acción
- ✅ **Visual**: Iconos y colores claros
- ✅ **Contextual**: Información específica por rol
- ✅ **Accesible**: Siempre visible en header

## 🔄 **MANTENIMIENTO**

### **Agregar Nuevos Tipos:**
1. Crear método en `NotificationService`
2. Agregar lógica de consulta
3. Definir URL de destino
4. Incluir en array de notificaciones

### **Modificar Frecuencia:**
```javascript
// En notification-header.blade.php
// Cambiar 30000 (30 segundos) por el valor deseado
setInterval(() => {
    this.loadNotifications();
}, 30000); // ← Modificar aquí
```

## 📝 **EJEMPLO DE USO**

### **Escenario:**
1. **Asesor** registra un préstamo → Estado "Pendiente"
2. **Jefe de Operaciones** ve notificación → "Préstamo #123 pendiente"
3. **Click** en notificación → Redirección a `/dashboard/prestamos/123/edit`
4. **Aprueba** el préstamo → Estado "Aprobado"
5. **Asesor** ve notificación → "Préstamo #123 fue Aprobado"

## 🎯 **RESULTADO FINAL**

El sistema proporciona un **flujo completo de notificaciones** que mejora significativamente la **productividad** y **comunicación** entre roles, sin impacto en la base de datos existente.
