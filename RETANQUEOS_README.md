# 🔄 MÓDULO DE RETANQUEOS

## 📋 DESCRIPCIÓN

El módulo de retanqueos permite gestionar solicitudes de retanqueo de grupos con préstamos activos. Los asesores pueden crear solicitudes que luego son aprobadas/rechazadas por jefes y super_admin, y finalmente ejecutadas para crear nuevos préstamos.

## 🎯 FUNCIONALIDADES PRINCIPALES

### 🔍 Para Asesores
- ✅ Ver grupos elegibles para retanqueo
- ✅ Crear nuevas solicitudes de retanqueo
- ✅ Configurar participantes (retanquea/no retanquea/nueva)
- ✅ Editar solicitudes pendientes
- ✅ Ver el historial de sus retanqueos

### 👔 Para Jefes y Super Admin
- ✅ Revisar todas las solicitudes pendientes
- ✅ Aprobar o rechazar retanqueos
- ✅ Ejecutar retanqueos aprobados
- ✅ Ver reportes completos

## 🚀 EJEMPLO DE USO

### Caso: Grupo "LAS EMPRENDEDORAS"
```
Préstamo Original: S/ 5,000
Pagado: S/ 3,000
Saldo Pendiente: S/ 2,000
Cuotas: 2/4 pagadas

RETANQUEO:
- Juana: S/ 500 (retanquea)
- María: S/ 500 (retanquea)  
- Luisa: S/ 500 (retanquea)
- Martha: S/ 0 (no retanquea)

RESULTADO:
- Nuevo Préstamo: S/ 1,500
- Cobertura del Antiguo: S/ 1,500
- A Entregar: S/ 0
- Saldo Restante: S/ 500 (deuda de Martha)
```

## 🛠️ COMANDOS ARTISAN

### Ver Estado de Retanqueos
```bash
php artisan retanqueo:status
```

### Crear Retanqueo de Prueba
```bash
php artisan db:seed --class=RetanqueoTestSeeder
```

## 📊 ESTADOS DEL RETANQUEO

| Estado | Descripción | Acciones Disponibles |
|--------|-------------|---------------------|
| `solicitud_pendiente` | Creado por asesor | Editar, Aprobar, Rechazar |
| `aprobado` | Aprobado por jefe | Ejecutar |
| `ejecutado` | Completado | Solo visualizar |
| `rechazado` | Rechazado | Solo visualizar |

## 🏗️ ARQUITECTURA

### Modelos
- `Retanqueo`: Información principal del retanqueo
- `RetanqueoIndividual`: Participación de cada cliente

### Servicios
- `RetanqueoService`: Lógica principal de negocio

### Recursos Filament
- `RetanqueoResource`: Interface principal del módulo

### Páginas
- `ListRetanqueos`: Lista con estadísticas
- `CreateRetanqueo`: Wizard de creación
- `ViewRetanqueo`: Vista detallada
- `EditRetanqueo`: Edición de solicitudes pendientes

## 🔒 PERMISOS POR ROL

### Asesor
- ✅ Crear solicitudes
- ✅ Ver sus propias solicitudes
- ✅ Editar solicitudes pendientes propias

### Jefe de Operaciones / Jefe de Créditos
- ✅ Ver todas las solicitudes
- ✅ Aprobar/rechazar solicitudes
- ✅ Ejecutar retanqueos aprobados

### Super Admin
- ✅ Acceso completo
- ✅ Eliminar solicitudes pendientes

## 📈 CÁLCULOS AUTOMÁTICOS

### Fórmulas
```
Aporte Cobertura = Saldo Pendiente Total / Cantidad que Retanquean
Monto a Entregar = Nuevo Préstamo - Total Cobertura
Saldo Restante = Saldo Pendiente - Total Cobertura
```

### Validaciones
- ✅ Montos mínimos y máximos por ciclo
- ✅ Solo grupos con préstamos activos
- ✅ Solo préstamos con saldos pendientes
- ✅ Verificación de integrantes activos

## 🚨 CASOS ESPECIALES

### Retanqueo Mayor que Deuda
Si el total del retanqueo excede la deuda pendiente:
- Se cubre toda la deuda antigua
- El préstamo antiguo se marca como "Finalizado"
- El excedente se entrega al grupo

### Cliente Nuevo en Retanqueo
- Se puede agregar un cliente nuevo al grupo
- Su participación se marca como "nueva"
- No aporta para cubrir la deuda antigua
- Recibe su monto completo

### Retanqueo Parcial
- Algunos integrantes retanquean, otros no
- Solo los que retanquean aportan para cobertura
- Los que no retanquean siguen con su deuda original

## 🔧 MANTENIMIENTO

### Logs
Todos los eventos importantes se registran en los logs:
- Creación de solicitudes
- Cambios de estado
- Ejecución de retanqueos
- Errores

### Base de Datos
Las tablas incluyen índices optimizados para consultas rápidas:
- `retanqueos`: Por estado, fecha, préstamo
- `retanqueos_individual`: Por participación, cliente

### Observadores
- `RetanqueoObserver`: Automatiza logging y notificaciones

## 🧪 TESTING

### Verificar Funcionamiento
1. Ejecutar: `php artisan retanqueo:status`
2. Verificar grupos elegibles
3. Crear retanqueo de prueba: `php artisan db:seed --class=RetanqueoTestSeeder`
4. Acceder al panel de administración
5. Probar flujo completo: Crear → Aprobar → Ejecutar

### Datos de Prueba
El seeder busca automáticamente grupos elegibles y crea un retanqueo de ejemplo con diferentes tipos de participación.

## ⚠️ CONSIDERACIONES IMPORTANTES

1. **No modificar** préstamos que tienen retanqueos en proceso
2. **Verificar** que los cálculos sean correctos antes de ejecutar
3. **Respaldar** base de datos antes de cambios importantes
4. **Revisar logs** en caso de errores

## 📞 SOPORTE

Para consultas técnicas o reportar errores, revisar:
1. Logs de Laravel: `storage/logs/laravel.log`
2. Estado con: `php artisan retanqueo:status`
3. Verificar migraciones: `php artisan migrate:status`

---

**Desarrollado para el Sistema Financiero EmprendeConmigo**  
*Versión: 1.0.0 - Fecha: Julio 2025*
