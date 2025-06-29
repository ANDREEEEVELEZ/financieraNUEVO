# MANUAL TÉCNICO COMPLETO
## SISTEMA DE GESTIÓN FINANCIERA - MICROCRÉDITOS GRUPALES

---

### 📋 INFORMACIÓN GENERAL DEL PROYECTO

**Nombre del Sistema:** Sistema de Gestión Financiera para Microcréditos Grupales  
**Versión:** 1.0  
**Tecnología Principal:** Laravel 12.0 + Filament 3.3  
**Base de Datos:** MySQL (Gestionada con MySQL Workbench)  
**Fecha de Elaboración:** Diciembre 2024  

---

## 📊 ARQUITECTURA DEL SISTEMA

### **Framework y Tecnologías**

| Componente | Tecnología | Versión |
|------------|------------|---------|
| **Backend Framework** | Laravel | 12.0 |
| **Panel Administrativo** | Filament PHP | 3.3 |
| **Base de Datos** | MySQL | 8.0+ |
| **Gestor de BD** | MySQL Workbench | Latest |
| **Frontend** | Blade Templates + Tailwind CSS | 4.0 |
| **Autenticación** | Laravel Sanctum + Filament Shield | 3.3 |
| **API IA** | OpenAI Laravel | Latest |
| **PDF Generation** | DomPDF | 3.1 |
| **Testing** | Pest PHP | 3.8 |

### **Estructura de Directorios**

```
financieraNUEVO/
├── app/
│   ├── Console/Commands/          # Comandos Artisan personalizados
│   ├── Filament/
│   │   └── Dashboard/
│   │       ├── Pages/             # Páginas personalizadas del panel
│   │       ├── Resources/         # Recursos CRUD de Filament
│   │       └── Widgets/           # Widgets del dashboard
│   ├── Http/
│   │   ├── Controllers/           # Controladores principales
│   │   └── Middleware/            # Middleware personalizado
│   ├── Models/                    # Modelos Eloquent
│   ├── Observers/                 # Observadores de modelos
│   ├── Policies/                  # Políticas de autorización
│   └── Providers/                 # Proveedores de servicios
├── config/                        # Archivos de configuración
├── database/
│   ├── migrations/                # Migraciones de BD
│   ├── seeders/                   # Seeders de datos
│   └── factories/                 # Factories para testing
├── resources/
│   ├── css/
│   ├── js/
│   ├── lang/                      # Archivos de idioma
│   └── views/                     # Vistas Blade
├── routes/                        # Definición de rutas
├── storage/                       # Archivos y logs
└── vendor/                        # Dependencias de Composer
```

---

## 🗄️ DISEÑO DE BASE DE DATOS

### **Diagrama de Entidad-Relación Conceptual**

```
PERSONAS ←→ USUARIOS ←→ ASESORES
    ↓                       ↓
CLIENTES ←→ GRUPOS ←→ PRESTAMOS
    ↓           ↓         ↓
GRUPO_CLIENTE   ↓    PRESTAMO_INDIVIDUAL
                ↓         ↓
        CUOTAS_GRUPALES  DETALLE_PAGO
                ↓         ↓
            PAGOS ←→ MORAS
                ↓
            INGRESOS
                
CATEGORIAS ←→ SUBCATEGORIAS ←→ EGRESOS
RETANQUEOS ←→ RETANQUEO_INDIVIDUAL
```

### **Descripción Detallada de Tablas**

#### **1. TABLAS PRINCIPALES**

##### **`personas`** - Datos personales base
```sql
- id (PK)
- DNI (8 dígitos, único)
- nombre
- apellidos 
- sexo (enum: Masculino, Femenino)
- fecha_nacimiento
- celular
- correo (único)
- direccion
- distrito
- estado_civil (enum: Soltero, Casado, Divorciado, Viudo)
```

##### **`users`** - Usuarios del sistema
```sql
- id (PK)
- persona_id (FK opcional)
- name
- email (único)
- password
- active (boolean, default true)
- roles (mediante Spatie Permission)
```

##### **`asesores`** - Asesores de crédito
```sql
- id (PK)
- persona_id (FK a personas)
- user_id (FK a users)
- codigo_asesor
- fecha_ingreso
- estado_asesor (Activo/Inactivo)
```

##### **`clientes`** - Clientes del sistema
```sql
- id (PK)
- persona_id (FK a personas)
- asesor_id (FK a asesores)
- infocorp (antecedentes crediticios)
- ciclo (1-4, determina límite de crédito)
- condicion_vivienda
- actividad (ocupación)
- condicion_personal
- estado_cliente
```

##### **`grupos`** - Grupos de clientes
```sql
- id (PK)
- asesor_id (FK a asesores)
- nombre_grupo
- numero_integrantes
- fecha_registro
- calificacion_grupo
- estado_grupo (Activo/Inactivo)
```

##### **`grupo_cliente`** - Tabla pivote grupos-clientes
```sql
- id (PK)
- grupo_id (FK a grupos)
- cliente_id (FK a clientes)
- fecha_ingreso
- fecha_salida (nullable)
- rol (Líder Grupal/Miembro)
- estado_grupo_cliente (Activo/Inactivo)
```

#### **2. MÓDULO DE PRÉSTAMOS**

##### **`prestamos`** - Préstamos grupales
```sql
- id (PK)
- grupo_id (FK a grupos)
- tasa_interes (integer, %)
- monto_prestado_total (decimal 10,2)
- monto_devolver (decimal 10,2)
- cantidad_cuotas
- fecha_prestamo
- frecuencia (mensual/quincenal/semanal)
- estado (Pendiente/Aprobado/Activo/Rechazado/Finalizado)
- calificacion
```

##### **`prestamo_individual`** - Detalles por cliente
```sql
- id (PK)
- prestamo_id (FK a prestamos)
- cliente_id (FK a clientes)
- monto_prestado_individual (decimal 10,2)
- monto_cuota_prestamo_individual (decimal 10,2)
- monto_devolver_individual (decimal 10,2)
- seguro (decimal 10,2)
- interes (decimal 10,2)
- estado
```

##### **`cuotas_grupales`** - Cuotas del préstamo
```sql
- id (PK)
- prestamo_id (FK a prestamos)
- numero_cuota
- monto_cuota_grupal (decimal 8,2)
- fecha_vencimiento
- saldo_pendiente (decimal 8,2)
- estado_cuota_grupal (vigente/mora/cancelada)
- estado_pago (pendiente/parcial/pagado)
```

#### **3. MÓDULO DE PAGOS Y MORAS**

##### **`pagos`** - Pagos realizados
```sql
- id (PK)
- cuota_grupal_id (FK a cuotas_grupales)
- tipo_pago
- codigo_operacion
- monto_pagado (decimal 10,2)
- monto_mora_pagada (decimal 10,2)
- fecha_pago
- estado_pago (pendiente/Aprobado/Rechazado)
- observaciones
```

##### **`moras`** - Control de moras
```sql
- id (PK)
- cuota_grupal_id (FK a cuotas_grupales)
- fecha_atraso
- estado_mora (pendiente/pagada/parcialmente_pagada)
```

##### **`detalles_pago`** - Detalle individual de pagos
```sql
- id (PK)
- pago_id (FK a pagos)
- prestamo_individual_id (FK a prestamo_individual)
- monto_pagado (decimal 10,2)
- estado_pago_individual (Pagada/Parcial/Mora)
```

#### **4. MÓDULO FINANCIERO**

##### **`categorias`** - Categorías de gastos
```sql
- id (PK)
- nombre_categoria
```

##### **`subcategorias`** - Subcategorías de gastos
```sql
- id (PK)
- categoria_id (FK a categorias)
- nombre_subcategoria
```

##### **`ingresos`** - Registro de ingresos
```sql
- id (PK)
- tipo_ingreso (transferencia/pago de cuota de grupo)
- pago_id (FK a pagos, nullable)
- grupo_id (FK a grupos, nullable)
- fecha_hora
- descripcion
- monto (decimal 10,2)
```

##### **`egresos`** - Registro de egresos
```sql
- id (PK)
- tipo_egreso (gasto/desembolso)
- fecha
- descripcion
- monto (decimal 10,2)
- prestamo_id (FK a prestamos, nullable)
- categoria_id (FK a categorias, nullable)
- subcategoria_id (FK a subcategorias, nullable)
```

#### **5. MÓDULO DE RETANQUEOS**

##### **`retanqueos`** - Retanqueos grupales
```sql
- id (PK)
- prestamo_id (FK a prestamos)
- grupo_id (FK a grupos)
- asesor_id (FK a asesores)
- monto_retanqueado
- monto_devolver
- monto_desembolsar
- cantidad_cuotas_retanqueo
- aceptado
- fecha_aceptacion
- estado_retanqueo
```

##### **`retanqueo_individual`** - Retanqueos individuales
```sql
- id (PK)
- retanqueo_id (FK a retanqueos)
- cliente_id (FK a clientes)
- monto_solicitado
- monto_desembolsar
- monto_cuota_retanqueo
- estado_retanqueo_individual
```

#### **6. MÓDULO DE ASISTENTE VIRTUAL**

##### **`consultas_asistente`** - Consultas del chat IA
```sql
- id (PK)
- user_id (FK a users)
- consulta (text)
- respuesta (text)
- timestamps
```

### **Relaciones Clave del Sistema**

1. **Usuario → Asesor → Clientes/Grupos**: Jerarquía de acceso
2. **Grupo → Clientes**: Relación Many-to-Many con historial
3. **Préstamo → Préstamos Individuales**: Composición de montos
4. **Cuotas → Pagos → Ingresos**: Flujo de dinero entrante
5. **Préstamos → Egresos**: Desembolsos automáticos
6. **Cuotas → Moras**: Control de vencimientos

---

## 🔐 SISTEMA DE ROLES Y PERMISOS

### **Roles Definidos**

#### **1. Super Admin**
- **Descripción**: Acceso total al sistema
- **Permisos**: Todos los módulos y funcionalidades
- **Restricciones**: Ninguna

#### **2. Jefe de Operaciones**
- **Descripción**: Supervisión operativa
- **Permisos**:
  - Ver todos los datos del sistema
  - Aprobar/rechazar préstamos
  - Gestionar asesores
  - Acceso a reportes financieros
- **Restricciones**: No puede modificar datos una vez aprobados

#### **3. Jefe de Créditos**
- **Descripción**: Gestión crediticia
- **Permisos**:
  - Ver todos los préstamos
  - Aprobar/rechazar solicitudes
  - Gestionar moras
  - Reportes crediticios
- **Restricciones**: No puede editar solicitudes de préstamos

#### **4. Asesor**
- **Descripción**: Gestión directa de clientes
- **Permisos**:
  - CRUD de clientes asignados
  - CRUD de grupos asignados
  - Crear/editar préstamos (solo estado Pendiente)
  - Registrar pagos
  - Ver reportes propios
- **Restricciones**: Solo ve datos de sus clientes/grupos asignados

### **Implementación de Permisos**

```php
// Middleware de verificación de roles activos
CheckUserActive::class

// Políticas implementadas
AsesorPolicy::class
ClientePolicy::class
GrupoPolicy::class
PrestamoPolicy::class
PagoPolicy::class

// Filtros por rol en recursos
public function scopeVisiblePorUsuario($query, $user)
{
    if ($user->hasRole('Asesor')) {
        $asesor = Asesor::where('user_id', $user->id)->first();
        return $query->where('asesor_id', $asesor->id);
    }
    return $query;
}
```

---

## 🏗️ COMPONENTES PRINCIPALES

### **1. PANEL ADMINISTRATIVO (Filament)**

#### **Estructura de Resources**

```php
app/Filament/Dashboard/Resources/
├── AsesorResource.php          # Gestión de asesores
├── ClienteResource.php         # Gestión de clientes
├── GrupoResource.php          # Gestión de grupos
├── PrestamoResource.php       # Gestión de préstamos
├── PagoResource.php          # Gestión de pagos
├── IngresosResource.php      # Gestión de ingresos
├── EgresosResource.php       # Gestión de egresos
└── UserResource.php          # Gestión de usuarios
```

#### **Páginas Personalizadas**

```php
app/Filament/Dashboard/Pages/
├── AsistenteVirtual.php      # Chat con IA
├── AsesorPage.php           # Dashboard del asesor
└── Moras.php               # Reporte de moras
```

#### **Widgets Implementados**

```php
app/Filament/Widgets/
├── ClienteStatsWidget.php    # Estadísticas de clientes
├── IngresosStatsWidget.php   # Estadísticas de ingresos
└── EgresosStatsWidget.php    # Estadísticas de egresos
```

### **2. MODELOS ELOQUENT**

#### **Principales Modelos y sus Relaciones**

```php
// Modelo Cliente con relaciones
class Cliente extends Model
{
    public function persona() { return $this->belongsTo(Persona::class); }
    public function asesor() { return $this->belongsTo(Asesor::class); }
    public function grupos() { 
        return $this->belongsToMany(Grupo::class, 'grupo_cliente')
                   ->withPivot('fecha_ingreso', 'fecha_salida', 'rol', 'estado_grupo_cliente');
    }
    public function prestamoIndividual() { return $this->hasMany(PrestamoIndividual::class); }
    
    // Métodos de negocio
    public function tieneGrupoActivo(): bool
    public function getGrupoActivoAttribute()
    public function scopeVisiblePorUsuario($query, $user)
}

// Modelo Préstamo con lógica de negocio
class Prestamo extends Model
{
    public function grupo() { return $this->belongsTo(Grupo::class); }
    public function cuotasGrupales() { return $this->hasMany(CuotasGrupales::class); }
    public function prestamoIndividual() { return $this->hasMany(PrestamoIndividual::class); }
    
    // Métodos de estado
    public function estaFinalizado(): bool
    public function getEstadoVisibleAttribute()
    public function aprobar()
    public function rechazar()
    public function actualizarEstadoAutomaticamente()
}

// Modelo Grupo con gestión de integrantes
class Grupo extends Model
{
    // Relaciones
    public function clientes() // Solo activos
    public function exIntegrantes() // Solo inactivos  
    public function todosLosIntegrantes() // Todos
    public function prestamos()
    public function asesor()
    
    // Métodos de negocio
    public function tienePrestamosActivos(): bool
    public function removerCliente($clienteId, $fechaSalida = null)
    public function transferirClienteAGrupo($clienteId, $nuevoGrupoId, $fechaSalida = null)
}
```

### **3. OBSERVADORES (OBSERVERS)**

#### **Automatización de Procesos**

```php
// PrestamoObserver - Automatiza flujos de préstamos
class PrestamoObserver
{
    public function updated(Prestamo $prestamo)
    {
        // Al aprobar préstamo:
        // 1. Crear cuotas grupales automáticamente
        // 2. Registrar egreso por desembolso
        // 3. Actualizar estado del grupo
        // 4. Sincronizar préstamos individuales
    }
}

// PrestamoIndividualObserver - Recálculos automáticos  
class PrestamoIndividualObserver
{
    public function updated(PrestamoIndividual $prestamoIndividual)
    {
        // 1. Recalcular interés y seguro según monto
        // 2. Actualizar cuotas grupales proporcionalmente
        // 3. Sincronizar montos totales del préstamo
    }
}

// PagoObserver - Gestión de pagos
class PagoObserver
{
    public function updated(Pago $pago)
    {
        // Al aprobar pago:
        // 1. Crear ingreso automático
        // 2. Actualizar saldo pendiente de cuota
        // 3. Actualizar estado de mora si aplica
        // 4. Verificar finalización de préstamo
    }
}
```

### **4. COMANDOS ARTISAN PERSONALIZADOS**

```bash
# Comando de sincronización de montos
php artisan prestamos:sincronizar-montos

# Comando de verificación de cuotas
php artisan cuotas:verificar
```

---

## 🤖 ASISTENTE VIRTUAL CON IA

### **Funcionalidades del Asistente**

#### **Capacidades Principales**
1. **Consultas en Lenguaje Natural**: El usuario puede preguntar en español coloquial
2. **Acceso Contextual**: Solo ve datos según el rol del usuario
3. **Generación de SQL**: Convierte preguntas a consultas SQL seguras
4. **Explicaciones Detalladas**: Proporciona contexto y análisis

#### **Implementación Técnica**

```php
class AsistenteVirtual extends Page
{
    // Configuración del contexto por rol
    public function generarContexto($user, $query)
    {
        $esquema = Storage::get('esquema_bd.txt');
        $relaciones = $this->obtenerRelacionesClave();
        $datosUsuario = $this->obtenerDatosSegunRol($user);
        
        return $this->construirPromptIA($esquema, $relaciones, $datosUsuario, $query);
    }
    
    // Filtrado de datos por rol
    public function aplicarFiltrosSeguridad($modelClass, $asesorId)
    {
        switch ($modelClass) {
            case Cliente::class:
                return $modelClass::where('asesor_id', $asesorId);
            case Grupo::class:
                return $modelClass::where('asesor_id', $asesorId);
            case Prestamo::class:
                return $modelClass::whereHas('grupo', fn($q) => $q->where('asesor_id', $asesorId));
            // ... más casos
        }
    }
}
```

#### **Ejemplos de Consultas Soportadas**

```
Usuario: "¿Cuántos clientes tengo en mora?"
IA: Genera SQL para contar clientes con cuotas en mora del asesor

Usuario: "Muestra los pagos del mes pasado por grupo"
IA: Agrupa pagos por grupo en el período especificado

Usuario: "¿Cuál es el grupo con mejor historial de pagos?"
IA: Calcula porcentajes de pagos puntuales por grupo
```

### **Seguridad del Asistente**

1. **Solo consultas SELECT**: No permite modificaciones
2. **Filtrado por rol**: Cada usuario solo ve sus datos
3. **Validación de queries**: Previene inyección SQL
4. **Límites de consulta**: Evita sobrecarga del sistema

---

## 📊 DASHBOARD Y REPORTES

### **Dashboard del Asesor (`AsesorPage.php`)**

#### **Métricas Principales**
```php
// Estadísticas calculadas
$totalClientes = $this->contarClientesActivos($asesor);
$totalGrupos = $this->contarGruposActivos($asesor);
$totalPrestamos = $this->contarPrestamos($asesor, $filtros);
$cuotasVigentes = $this->contarCuotasPorEstado($asesor, 'vigente');
$cuotasEnMora = $this->contarCuotasPorEstado($asesor, 'mora');
$pagosAprobados = $this->contarPagosPorEstado($asesor, 'Aprobado');
$pagosPendientes = $this->contarPagosPorEstado($asesor, 'pendiente');
$pagosRechazados = $this->contarPagosPorEstado($asesor, 'Rechazado');
```

#### **Gráficos Implementados**
1. **Gráfico de Barras**: Estados de cuotas por cantidad
2. **Gráfico de Líneas**: Pagos por fecha (últimos 30 días)
3. **Gráfico Circular**: Distribución de estados de pagos
4. **Gráfico de Barras Horizontal**: Moras por grupo

#### **Filtros Disponibles**
- **Rango de fechas**: Desde/Hasta
- **Aplicación automática**: Afecta todas las métricas y gráficos

### **Página de Moras (`Moras.php`)**

#### **Funcionalidades**
1. **Vista detallada**: Lista todas las cuotas en mora
2. **Filtros avanzados**: Por grupo, estado, fechas
3. **Exportación PDF**: Reporte completo de moras
4. **Acciones rápidas**: Crear pagos directamente

#### **Información Mostrada**
```php
// Datos por cuota en mora
$cuota->prestamo->grupo->nombre_grupo
$cuota->numero_cuota
$cuota->fecha_vencimiento
$cuota->monto_cuota_grupal
$cuota->saldo_pendiente
$cuota->mora->fecha_atraso
$cuota->mora->estado_mora
```

---

## ⚙️ CONFIGURACIÓN Y DESPLIEGUE

### **Requisitos del Sistema**

#### **Servidor**
- **PHP**: 8.2 o superior
- **Composer**: 2.0+
- **Node.js**: 18+ (para assets)
- **Extensiones PHP**: mysql, gd, intl, zip, bcmath, pdo_mysql

#### **Base de Datos**
- **MySQL**: 8.0+ (recomendado)
- **MySQL Workbench**: Para administración visual de la BD
- **Configuración**: UTF8MB4 character set

### **Instalación**

#### **1. Clonar Repositorio**
```bash
git clone [url-repositorio]
cd financieraNUEVO
```

#### **2. Instalación de Dependencias**
```bash
# Dependencias PHP
composer install

# Dependencias Node.js
npm install

# Compilar assets
npm run build
```

#### **3. Configuración del Entorno**
```bash
# Copiar archivo de configuración
cp .env.example .env

# Generar key de aplicación
php artisan key:generate

# Configurar base de datos en .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=financiera_nuevo
DB_USERNAME=root
DB_PASSWORD=tu_password
```

#### **4. Configuración de Base de Datos**
```bash
# Crear base de datos MySQL usando MySQL Workbench:
# 1. Abrir MySQL Workbench
# 2. Conectar al servidor MySQL local
# 3. Ejecutar: CREATE DATABASE financiera_nuevo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
# 4. Crear usuario específico (opcional):
#    CREATE USER 'financiera_user'@'localhost' IDENTIFIED BY 'secure_password';
#    GRANT ALL PRIVILEGES ON financiera_nuevo.* TO 'financiera_user'@'localhost';

# Ejecutar migraciones
php artisan migrate

# Ejecutar seeders (opcional)
php artisan db:seed
```

#### **5. Configuración de Filament**
```bash
# Crear usuario admin
php artisan shield:super-admin

# Limpiar caché
php artisan config:clear
php artisan cache:clear
```

### **Configuración de Producción**

#### **Variables de Entorno Críticas**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tusitio.com

# Base de datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=financiera
DB_USERNAME=usuario
DB_PASSWORD=contraseña

# OpenAI (para asistente)
OPENAI_API_KEY=sk-...

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email
MAIL_PASSWORD=tu-password
```

#### **Optimizaciones**
```bash
# Optimizar autoload
composer install --optimize-autoloader --no-dev

# Cachear configuración
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Configurar permisos
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## 🔧 MANTENIMIENTO Y ADMINISTRACIÓN

### **Comandos de Mantenimiento**

#### **Verificación de Integridad**
```bash
# Sincronizar montos de préstamos
php artisan prestamos:sincronizar-montos

# Verificar cuotas grupales
php artisan cuotas:verificar

# Limpiar logs antiguos
php artisan log:clear

# Optimizar base de datos
php artisan db:optimize
```

#### **Backups Recomendados**
```bash
# Backup de base de datos MySQL
mysqldump -u root -p financiera_nuevo > backups/database_$(date +%Y%m%d_%H%M%S).sql

# Backup usando MySQL Workbench:
# 1. Abrir MySQL Workbench
# 2. Conectar al servidor
# 3. Server > Data Export
# 4. Seleccionar schema 'financiera_nuevo'
# 5. Export to Self-Contained File
# 6. Incluir rutinas y eventos si los hay

# Backup de archivos subidos
tar -czf backups/storage_$(date +%Y%m%d_%H%M%S).tar.gz storage/app

# Backup completo del sistema
tar -czf backups/sistema_completo_$(date +%Y%m%d_%H%M%S).tar.gz \
    --exclude=node_modules \
    --exclude=vendor \
    --exclude=storage/logs \
    .
```

### **Monitoreo del Sistema**

#### **Logs Importantes**
```bash
# Logs de aplicación
tail -f storage/logs/laravel.log

# Logs de errores de préstamos
grep "PrestamoObserver" storage/logs/laravel.log

# Logs de consultas del asistente IA
grep "AsistenteVirtual" storage/logs/laravel.log
```

#### **Métricas a Monitorear**
1. **Usuarios activos concurrentes**
2. **Tiempo de respuesta de consultas**
3. **Errores en observers**
4. **Uso de API de OpenAI**
5. **Tamaño de base de datos**

### **Administración con MySQL Workbench**

#### **Conexión y Configuración Inicial**
```sql
-- Configurar conexión en MySQL Workbench
-- Hostname: localhost (o IP del servidor)
-- Port: 3306
-- Username: root (o usuario específico)
-- Password: [tu_password]
-- Schema: financiera_nuevo
```

#### **Consultas de Monitoreo Útiles**
```sql
-- Verificar estado de las tablas principales
SELECT 
    TABLE_NAME as Tabla,
    TABLE_ROWS as Registros,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as 'Tamaño(MB)'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'financiera_nuevo'
ORDER BY TABLE_ROWS DESC;

-- Préstamos por estado
SELECT 
    estado,
    COUNT(*) as cantidad,
    SUM(monto) as monto_total
FROM prestamos 
GROUP BY estado;

-- Clientes con mora
SELECT 
    c.nombre,
    c.apellido,
    p.monto,
    p.fecha_vencimiento,
    DATEDIFF(NOW(), p.fecha_vencimiento) as dias_vencidos
FROM clientes c
JOIN prestamos p ON c.id = p.cliente_id
WHERE p.estado = 'vencido'
ORDER BY dias_vencidos DESC;

-- Rendimiento de asesores
SELECT 
    a.nombre,
    COUNT(p.id) as total_prestamos,
    SUM(p.monto) as monto_gestionado,
    AVG(p.monto) as promedio_prestamo
FROM asesors a
LEFT JOIN prestamos p ON a.id = p.asesor_id
GROUP BY a.id
ORDER BY monto_gestionado DESC;
```

#### **Índices Recomendados para Optimización**
```sql
-- Índices para mejorar rendimiento
CREATE INDEX idx_prestamos_estado ON prestamos(estado);
CREATE INDEX idx_prestamos_fecha_vencimiento ON prestamos(fecha_vencimiento);
CREATE INDEX idx_prestamos_cliente_id ON prestamos(cliente_id);
CREATE INDEX idx_cuotas_grupales_fecha ON cuotas_grupales(fecha_pago);
CREATE INDEX idx_pagos_prestamo_id ON pagos(prestamo_id);
CREATE INDEX idx_clientes_activo ON clientes(activo);

-- Índice compuesto para consultas frecuentes
CREATE INDEX idx_prestamos_cliente_estado ON prestamos(cliente_id, estado);
CREATE INDEX idx_cuotas_grupo_fecha ON cuotas_grupales(grupo_id, fecha_pago);
```

#### **Procedimientos de Backup y Restauración**
```bash
# Backup programado desde MySQL Workbench
# 1. Server > Data Export
# 2. Seleccionar schema 'financiera_nuevo'
# 3. Export Options:
#    - Export to Self-Contained File
#    - Include Create Schema
#    - Dump Stored Procedures and Functions
#    - Dump Events
#    - Dump Triggers

# Restauración desde línea de comandos
mysql -u root -p financiera_nuevo < backup_financiera_nuevo.sql

# Verificación post-restauración
mysql -u root -p -e "USE financiera_nuevo; SHOW TABLES; SELECT COUNT(*) FROM prestamos;"
```

#### **Mantenimiento de Base de Datos**
```sql
-- Optimizar tablas (ejecutar mensualmente)
OPTIMIZE TABLE prestamos, clientes, cuotas_grupales, pagos;

-- Analizar tablas para estadísticas
ANALYZE TABLE prestamos, clientes, cuotas_grupales, pagos;

-- Verificar integridad
CHECK TABLE prestamos, clientes, cuotas_grupales, pagos;

-- Limpiar logs binarios (si están habilitados)
PURGE BINARY LOGS BEFORE DATE(NOW() - INTERVAL 7 DAY);
```

---

## 🚨 SOLUCIÓN DE PROBLEMAS COMUNES

### **Problemas de Autenticación**

#### **Usuario no puede acceder**
```bash
# Verificar estado del usuario
php artisan tinker
>>> User::where('email', 'usuario@email.com')->first()->active

# Reactivar usuario
>>> User::where('email', 'usuario@email.com')->update(['active' => true])
```

#### **Problemas de roles**
```bash
# Verificar roles del usuario
>>> User::find(1)->roles

# Asignar rol
>>> User::find(1)->assignRole('Asesor')

# Regenerar permisos
php artisan shield:install --fresh
```

### **Problemas de Datos**

#### **Montos descuadrados**
```bash
# Ejecutar sincronización
php artisan prestamos:sincronizar-montos

# Verificar en tinker
php artisan tinker
>>> $prestamo = Prestamo::find(1)
>>> $prestamo->sincronizarMontosTotal()
```

#### **Cuotas inconsistentes**
```bash
# Verificar cuotas
php artisan cuotas:verificar

# Recalcular manualmente
php artisan tinker
>>> $prestamo = Prestamo::find(1)
>>> $prestamo->cuotasGrupales()->delete()
>>> # Cambiar estado a Pendiente y volver a Aprobado para regenerar
```

### **Problemas de Performance**

#### **Consultas lentas**
```bash
# Habilitar log de consultas en .env
DB_LOG_QUERIES=true

# Verificar índices faltantes
php artisan db:optimize

# Analizar consultas N+1
php artisan telescope:install # Solo en desarrollo
```

#### **Memoria insuficiente**
```bash
# Aumentar límite en php.ini
memory_limit = 512M

# Optimizar consultas con paginación
# En los recursos de Filament usar ->simplePaginate()
```

---

## 🔒 SEGURIDAD

### **Medidas Implementadas**

#### **Autenticación y Autorización**
1. **Hashing de contraseñas**: Bcrypt por defecto
2. **Middleware de verificación**: CheckUserActive
3. **Políticas granulares**: Por recurso y acción
4. **Filtrado por rol**: En todas las consultas

#### **Protección de Datos**
1. **Validación de entrada**: En todos los formularios
2. **Mass assignment protection**: En modelos Eloquent
3. **SQL injection prevention**: Uso de Eloquent ORM
4. **XSS protection**: Escape automático en Blade

#### **Configuraciones de Seguridad**
```php
// config/app.php
'debug' => env('APP_DEBUG', false), // false en producción

// Middleware aplicados
CheckUserActive::class,
Authenticate::class,
VerifyCsrfToken::class,

// Validaciones en formularios
'monto' => 'required|numeric|min:0|max:999999.99',
'DNI' => 'required|digits:8|unique:personas,DNI',
'email' => 'required|email|unique:users,email',
```

### **Recomendaciones Adicionales**

#### **Servidor**
1. **HTTPS obligatorio** en producción
2. **Firewall configurado** (puertos 80, 443, 22)
3. **Actualizaciones regulares** del sistema operativo
4. **Backups automáticos** diarios

#### **Aplicación**
1. **Rotación de API keys** (OpenAI)
2. **Logs de auditoría** activados
3. **Límites de rate limiting** en rutas públicas
4. **Validación de archivos** subidos

---

## 📈 ESCALABILIDAD Y MEJORAS FUTURAS

### **Optimizaciones Planificadas**

#### **Performance**
1. **Cache de consultas frecuentes**: Redis/Memcached
2. **Índices de base de datos**: En campos de búsqueda
3. **Queue jobs**: Para procesos pesados
4. **CDN**: Para assets estáticos

#### **Funcionalidades**
1. **API REST**: Para integraciones externas
2. **Notificaciones**: Email/SMS para vencimientos
3. **Reportes avanzados**: Análisis predictivo
4. **App móvil**: Para asesores en campo

### **Monitoreo y Analytics**

#### **Métricas de Negocio**
1. **Tasa de morosidad** por asesor/grupo
2. **Tiempo promedio** de aprobación
3. **Ciclo de vida** del cliente
4. **ROI por producto** crediticio

#### **Métricas Técnicas**
1. **Tiempo de respuesta** por endpoint
2. **Uso de memoria** y CPU
3. **Errores por minuto**
4. **Disponibilidad del sistema**

---

## 📚 DOCUMENTACIÓN TÉCNICA ADICIONAL

### **Convenciones de Código**

#### **Laravel/PHP**
- **PSR-12**: Estándares de código
- **Naming**: CamelCase para clases, snake_case para variables
- **Comentarios**: PhpDoc para métodos públicos
- **Testing**: Pest PHP para pruebas

#### **Base de Datos**
- **Naming**: snake_case para tablas y columnas  
- **Foreign Keys**: tabla_id (ej: cliente_id)
- **Timestamps**: created_at, updated_at automáticos
- **Soft Deletes**: Donde sea aplicable

#### **Frontend**
- **Tailwind CSS**: Utility-first
- **Blade Components**: Reutilizables
- **AlpineJS**: Para interactividad
- **Chart.js**: Para gráficos

### **Testing**

#### **Estructura de Pruebas**
```bash
tests/
├── Feature/               # Pruebas de integración
│   ├── GestionIntegrantesTest.php
│   └── PrestamoTest.php
├── Unit/                  # Pruebas unitarias
│   └── PanelAsesorTest.php
└── TestCase.php          # Clase base
```

#### **Ejecutar Pruebas**
```bash
# Todas las pruebas
php artisan test

# Pruebas específicas
php artisan test --filter GestionIntegrantesTest

# Con cobertura
php artisan test --coverage
```

---

## 📞 SOPORTE Y CONTACTO

### **Información del Desarrollador**

**Proyecto**: Sistema de Gestión Financiera - Microcréditos Grupales  
**Versión**: 1.0  
**Framework**: Laravel 12.0 + Filament 3.3  
**Fecha**: Diciembre 2024  

### **Documentación Adicional**

1. **Laravel**: https://laravel.com/docs
2. **Filament**: https://filamentphp.com/docs
3. **Tailwind CSS**: https://tailwindcss.com/docs
4. **Spatie Permission**: https://spatie.be/docs/laravel-permission

### **Notas Finales**

Este sistema ha sido diseñado específicamente para instituciones financieras que manejan microcréditos grupales. La arquitectura modular permite fácil mantenimiento y escalabilidad. Todas las funcionalidades han sido probadas en entorno de desarrollo y están listas para producción.

---

**© 2024 - Sistema de Gestión Financiera**  
*Manual Técnico Completo v1.0*
