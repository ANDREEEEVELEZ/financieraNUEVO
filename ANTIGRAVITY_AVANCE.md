## 1. Resumen Ejecutivo y Dominio de Negocio
El proyecto es una plataforma de gestión financiera y préstamos. El sistema administra el ciclo de vida completo de un crédito: desde su pre-aprobación, firma, desembolso, hasta el pago de cuotas y eventual refinanciamiento (retanqueo). La plataforma atiende a distintos roles, priorizando la vista de "Asesor", quien solo debe gestionar su propia cartera.

## 2. Stack Tecnológico y Arquitectura Base
- **Backend:** Laravel 12 (PHP)
- **Panel Administrativo:** Filament 3.3 (Construido sobre Livewire y TailwindCSS 4)
- **Frontend/Assets:** Vite
- **Base de Datos:** Relacional (MySQL/PostgreSQL), gestionada estrictamente mediante migraciones de Laravel.

### Patrones de Arquitectura Detectados:
- **Filament Resources:** Uso intensivo de clases *Resource*, *Pages*, y *Widgets* para construir la UI.
- **Observers para Caché:** Uso de eventos de Eloquent (`saved`, `deleted`, etc.) dentro de *Observers* para invalidar caché inteligentemente.
- **Servicios Lógicos:** Separación de responsabilidades mediante clases de Servicio (ej. `RetanqueoService.php`) para cálculos financieros.

---

## 3. Requerimientos Funcionales Detallados

### 3.1. Gestión de la Máquina de Estados (Préstamos)
El ciclo de vida de un préstamo (modelo `Prestamo`) sigue un pipeline estricto:
- **Estados principales:** `Por Firmar` -> `Firmado` -> `Por Desembolsar` -> `Activo`.
- **Regla de negocio:** Entre las fases iniciales (antes del desembolso), los asesores tienen acciones específicas para modificar/reducir el monto aprobado si es necesario, pero las validaciones deben restringir cualquier cambio a las cuotas una vez que el préstamo pasa a `Activo`.

### 3.2. Gestión de Cuotas y Dashboards de Cobranza
- **Requerimiento:** Visibilidad en tiempo real de obligaciones a corto plazo.
- **Implementación:** `CuotasVigentesWidget`. Este widget debe evaluar la fecha actual (`today`, `this week`) y desplegar los pagos inminentes.
- **Restricción de Rol:** Si el usuario es Asesor, la consulta en base de datos (`Eloquent Query`) debe estar restringida (`scoped`) estrictamente a la cartera de clientes de ese asesor.

### 3.3. Trazabilidad y Log de Auditoría
- **Requerimiento:** Todo cambio en los registros financieros (especialmente en `Prestamo`) debe dejar una huella inmutable.
- **Implementación:** Existe un *Timeline* de trazabilidad visible en la página de detalles (`ViewPrestamo`). El sistema registra el autor del cambio (`user_id`), la acción exacta, los valores previos y los nuevos valores.

### 3.4. Renovaciones y Retanqueos
- **Requerimiento:** Capacidad de restructurar una deuda existente (comúnmente llamado "Retanqueo").
- **Implementación:** Lógica delegada a clases como `RetanqueoService`. Implica calcular saldo deudor, aplicar pagos, crear un nuevo préstamo consolidado y cerrar el anterior.

---

## 4. Requerimientos No Funcionales Detallados

### 4.1. Rendimiento del Frontend ("Fortalecido y Rico")
- **Meta:** Tiempos de carga ágiles y reactividad en Livewire, sin bloqueos del *First Byte*.
- **Técnica:** Mantener un HTML limpio, uso óptimo de caché para datos estáticos, y evitar procesamientos sincrónicos pesados que congelen la interfaz de Filament.

### 4.2. Eficiencia de Base de Datos y Caché Escalonable
- **Meta:** Cero consultas N+1 en las tablas del Panel.
- **Técnica:** Uso obligatorio de *Eager Loading* (`with(['relacion1', 'relacion2'])`) en las *Table Queries* de Filament.
- **Técnica de Caché:** Implementación actual basada en archivos (`file-based`). Cualquier consulta recurrente y costosa se encapsula en `Cache::remember()`. La arquitectura está preparada para hacer un `drop-in` de Redis cuando la infraestructura lo permita.

### 4.3. Calidad de Código (*Zero IDE Warnings Policy*)
- **Meta:** Mantenibilidad extrema y código auto-explicativo.
- **Reglas del Orquestador:** 
  1. No introducir advertencias de tipado estricto. Declarar return types (`: void`, `: array`, `: string`) donde aplique.
  2. Eliminar métodos deprecados o código legado (ej. la remoción previa del método `ejecutar` hardcodeado en `ViewPrestamo.php`).
  3. No inyectar `hardcoding` (usar Enums, Constantes o Configuración).

---

## 5. Planes e Implementaciones

### 5.1. Implementaciones Exitosas (Completadas)
1. **Refactorización del Workflow de Préstamos:** Separación exitosa de "Por Firmar" a "Firmado", añadiendo granularidad al flujo.
2. **Dashboard de Fase 4 (`CuotasVigentesWidget`):** Implementado con soporte a filtros de asesores.
3. **Pestaña de Trazabilidad (`ViewPrestamo`):** Log de auditoría integrado visualmente.
4. **Plan de Optimización de Rendimiento (Fase 1):** Inyección de *Eager Loading* y creación de *Observers* para la gestión de caché. Se purgaron más de 30 advertencias de IDE.

### 5.2. Planes No Exitosos o Pospuestos (En Pausa)
1. **Migración Inmediata a Redis:** Postergado temporalmente; el sistema sigue usando caché de archivos. El agente orquestador debe seguir utilizando la API facade `Cache::` nativa de Laravel para no romper la compatibilidad futura.
2. **Validación Vía Edge Computing:** Descartado en favor de robustecer los middlewares del servidor y mantener centralizada la lógica en el backend.
3. **Reset Frecuente de Migraciones:** Al inicio del proyecto se utilizó extensivamente `migrate:reset`, indicando fricción estructural. El agente debe priorizar crear migraciones incrementales seguras en adelante en lugar de rehacer esquemas.

## 6. Estado y Foco Actual (Fase 4)
El ecosistema es estable. El esfuerzo principal actual para el agente orquestador es:
- Perfeccionar y expandir módulos paralelos de la Fase 4 (e.g. Controladores de Grupos y consolidar los servicios de Retanqueos).
- Mantener estrictamente el rendimiento logrado (no introducir N+1) en las nuevas vistas y formularios de Filament.
