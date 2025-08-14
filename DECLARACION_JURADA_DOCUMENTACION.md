# Documentación: Declaración Jurada PEP

## Descripción
Este módulo permite generar automáticamente la **Declaración Jurada de Conocimiento del Cliente bajo el Régimen General – Persona Natural** para cumplir con los requisitos normativos de PEP (Personas Expuestas Políticamente).

## Características Implementadas

### ✅ Funcionalidades Principales
1. **Botón en Módulo de Clientes**: Accesible desde la tabla de clientes
2. **Auto-completado de Datos**: Los campos 1-8 se llenan automáticamente desde la base de datos
3. **Lógica Condicional PEP**: 
   - Si marca "NO SOY" o "NO HE SIDO" → oculta campos adicionales
   - Si marca "SI SOY" o "SI HE SIDO" → muestra campos de parientes
4. **Campos Fijos por Defecto**:
   - Campo 9: "PRESTAMO" (no editable)
   - Campo 11: "Por mi mismo" (préstamo personal)
   - Campo 11.1: "NO APLICA" automático
5. **Generación de PDF**: Formato exacto al documento original
6. **Sin Base de Datos**: Los documentos no se guardan, solo se descargan

### ✅ Resolución de Problemas Anteriores
- **❌ Líneas desalineadas**: Solucionado con posicionamiento CSS exacto
- **❌ Texto mal posicionado**: Solucionado con coordenadas precisas
- **❌ Formularios HTML problemáticos**: Eliminados, ahora usa plantilla PDF directa

## Cómo Usar

### 1. Acceder a la Funcionalidad
1. Ir al módulo **Clientes** en Filament
2. Buscar el cliente deseado
3. Hacer clic en el botón **"Declaración Jurada PEP"** (icono de documento)

### 2. Completar el Formulario
1. **Información PEP**: Responder preguntas obligatorias
   - ¿Es o ha sido PEP?
   - ¿Ha sido colaborador directo?
   - Si aplica: ¿Es pariente de PEP?
   - Si tiene parientes PEP: Agregar datos de parientes

2. **Información Adicional**: Completar campos opcionales
   - Teléfono fijo
   - Propósito de la relación comercial
   - Observaciones adicionales

### 3. Generar PDF
1. Hacer clic en **"Generar PDF"**
2. El documento se descarga automáticamente
3. El archivo incluye fecha actual y datos del cliente

## Estructura de Datos Auto-completados

### Desde la Base de Datos del Cliente:
- **Campo 1**: Nombres y apellidos
- **Campo 2**: DNI (siempre marcado)
- **Campo 3**: Nacionalidad (fijo: "PERUANA")
- **Campo 4**: Estado civil (desde persona.estado_civil)
- **Campo 6**: Dirección completa y distrito
- **Campo 7**: Ocupación/actividad
- **Campo 8**: Celular y correo electrónico

### Campos Fijos del Sistema:
- **Campo 9**: "PRESTAMO" (no editable)
- **Campo 11**: "Por mi mismo" marcado
- **Campo 11.1**: Información del cliente actual
- **Fecha**: Fecha actual del sistema
- **Firma**: Nombre completo y DNI del cliente

## Archivos Implementados

### Backend
- `app/Http/Controllers/DeclaracionJuradaController.php`: Controlador principal
- `routes/web.php`: Rutas para generación de PDF
- `app/Filament/Dashboard/Resources/ClienteResource.php`: Integración con módulo

### Frontend
- `resources/views/pdf/declaracion-jurada.blade.php`: Plantilla HTML del documento

### Dependencias
- `dompdf/dompdf`: Librería para generación de PDF

## Validaciones Implementadas

### Campos Obligatorios:
- Es o ha sido PEP
- Ha sido colaborador directo

### Campos Condicionales:
- Pariente PEP (solo si es PEP)
- Datos de parientes (solo si tiene parientes PEP)

### Campos Opcionales:
- Teléfono fijo
- Propósito de relación comercial
- Observaciones adicionales

## Permisos y Seguridad

### Roles Autorizados:
- super_admin
- Jefe de operaciones
- Jefe de creditos
- Asesor

### Middleware Aplicado:
- Autenticación requerida
- Usuario activo
- Verificación de roles

## Mantenimiento

### Para Actualizar el Formato:
1. Editar `resources/views/pdf/declaracion-jurada.blade.php`
2. Ajustar estilos CSS en la sección `<style>`
3. Limpiar caché: `php artisan view:clear`

### Para Agregar Campos:
1. Actualizar validación en `DeclaracionJuradaController.php`
2. Agregar campo en formulario de `ClienteResource.php`
3. Actualizar plantilla PDF

### Para Debugging:
1. Verificar logs en `storage/logs/laravel.log`
2. Probar con diferentes estados civiles y condiciones PEP
3. Validar alineación del PDF en diferentes navegadores

## Notas Técnicas

- El PDF se genera usando DOMPDF con fuente DejaVu Sans
- Tamaño de página: A4 Portrait
- Los datos no se persisten en base de datos
- El formulario es reactivo (campos se muestran/ocultan según respuestas)
- Compatible con todos los navegadores modernos
