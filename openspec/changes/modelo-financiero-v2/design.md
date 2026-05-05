# Design: modelo-financiero-v2

**Status:** Designed | **Date:** 2026-04-29 | **Artifact Store:** hybrid

> Este documento define las decisiones arquitecturales y los contratos de implementacion para los 5 ejes del change `modelo-financiero-v2`. NO incluye breakdown de tasks (eso es responsabilidad de `sdd-tasks`).

---

## 0. Patron Transversal

### 0.1 Principios de diseno aplicados

| Principio | Como se aplica |
|-----------|----------------|
| **Migraciones incrementales** | Cada eje agrega migraciones nuevas con timestamp `2026_04_29_*` o posterior. Nunca se edita una migracion existente. |
| **Service layer** | Toda mutacion multi-tabla y/o auditable vive en un `*Service::metodo(): TipoRetorno`. Los modelos solo exponen estado. |
| **Atomicidad explicita** | Toda operacion multi-write usa `DB::transaction(fn () => ...)`. Si falla un paso, rollback completo. |
| **Audit polimorfico** | `AuditService::registrar(accion, $modelo, $antes, $despues, $motivo)` se invoca **dentro** de la transaccion, sobre el modelo principal de la operacion. |
| **Strict types** | Toda firma nueva declara return types y argument types. Cero ambiguedad. |
| **Sistema canonico** | Todo flujo NUEVO de pagos/cuotas escribe en `cuota_individual` + `aplicacion_pago`. El legacy queda solo lectura. |
| **Test-first (Pest 3.8)** | Cada eje incluye tests Pest red-green-refactor antes de implementar. |

### 0.2 Convenciones de naming para migraciones

```
2026_04_29_HHMMSS_<accion>_<entidad>.php
```

Ejemplos validos:
- `2026_04_29_100001_normalize_prestamo_individual_estado_data.php`
- `2026_04_29_100002_change_prestamo_individual_estado_to_string.php`
- `2026_04_29_100003_make_pagos_cuota_grupal_id_nullable.php`

### 0.3 Politica de rollback

Cada migracion DEBE implementar `down()` reversible. Excepcion documentada: cuando una migracion contiene un `change()` sobre columna con FK indexada, el `down()` puede dejarse vacio si revertir el tipo es semanticamente imposible (caso GAP-01 con FK constraint). Documentar el motivo en docblock.

---

## 1. Eje 1 — GAP-03: Fix `prestamo_individual.estado`

### 1.1 Decision tecnica

**Migracion en DOS pasos separados** (no un solo archivo) para garantizar:
1. Normalizacion de datos sucios PRIMERO (UPDATE explicito).
2. Cambio de tipo DESPUES (DDL `change()`).

**Rationale:** ejecutar `change()` antes de normalizar puede fallar en MySQL si hay valores `NULL` o `0.00` que no encajan en el nuevo tipo string sin conversion explicita. Separarlo aisla el riesgo y permite rollback intermedio.

**Rejected alternative:** Un solo archivo con `DB::statement` y `change()`. Rechazado: mezcla responsabilidades, complica el `down()`.

### 1.2 Migraciones

#### 1.2.1 `2026_04_29_100001_normalize_prestamo_individual_estado_data.php`

```php
public function up(): void
{
    // Normaliza valores existentes ANTES de cambiar el tipo:
    //   NULL  -> 'Pendiente'
    //   0.00  -> 'Pendiente' (default actual del bug decimal)
    //   resto -> se queda como está (cast posterior lo manejará)
    DB::table('prestamo_individual')
        ->whereNull('estado')
        ->orWhere('estado', '0.00')
        ->orWhere('estado', '0')
        ->update(['estado' => 'Pendiente']);
}

public function down(): void
{
    // No-op: la normalización a 'Pendiente' es idempotente
    // y revertirla a NULL/0.00 perdería datos.
}
```

#### 1.2.2 `2026_04_29_100002_change_prestamo_individual_estado_to_string.php`

```php
public function up(): void
{
    Schema::table('prestamo_individual', function (Blueprint $table) {
        $table->string('estado', 50)->default('Pendiente')->change();
    });
}

public function down(): void
{
    // Documentado: revertir a decimal corrompería datos. No-op intencional.
}
```

### 1.3 Cambios en `app/Models/PrestamoIndividual.php`

```php
// Constantes alineadas con Prestamo::ESTADOS pero acotadas al ciclo
// individual (no incluye AL_DIA, EN_MORA porque son del préstamo padre).
public const ESTADO_PENDIENTE   = 'Pendiente';
public const ESTADO_APROBADO    = 'Aprobado';
public const ESTADO_FIRMADO     = 'Firmado';
public const ESTADO_ACTIVO      = 'Activo';
public const ESTADO_FINALIZADO  = 'Finalizado';
public const ESTADO_RECHAZADO   = 'Rechazado';
public const ESTADO_CANCELADO   = 'Cancelado';

public const ESTADOS = [
    self::ESTADO_PENDIENTE,
    self::ESTADO_APROBADO,
    self::ESTADO_FIRMADO,
    self::ESTADO_ACTIVO,
    self::ESTADO_FINALIZADO,
    self::ESTADO_RECHAZADO,
    self::ESTADO_CANCELADO,
];

protected $casts = [
    'monto_prestado_individual'        => 'decimal:2',
    'monto_cuota_prestamo_individual'  => 'decimal:2',
    'monto_devolver_individual'        => 'decimal:2',
    'seguro'                           => 'decimal:2',
    'interes'                          => 'decimal:2',
    // 'estado' eliminado del cast — string nativo
];

public function estaFinalizado(): bool
{
    return $this->estado === self::ESTADO_FINALIZADO;
}
```

### 1.4 Edge cases

| Caso | Manejo |
|------|--------|
| Valores sucios ya en BD (`'0.00'`, `''`, `NULL`) | Migracion 1.2.1 los normaliza a `'Pendiente'`. |
| Valores con espacios o casing distinto (`' aprobado '`) | Out of scope: asumimos solo escrituras programaticas. Si aparecen, agregar `trim()` + capitalizacion en migracion. |
| Test que escribe estado como float | Falla en este eje: el cast string ahora rechaza floats no parseables como string limpio (MySQL los convierte pero el test detecta el bug). |

### 1.5 Decision sobre transacciones

Las dos migraciones NO van en `DB::transaction` explicito: Laravel ya envuelve cada migracion en su propia transaccion via `Schema`. Ejecutarlas en archivos separados garantiza commit intermedio y rollback granular.

---

## 2. Eje 2 — GAP-05: Seeders rebuild

### 2.1 Decision tecnica

**DatabaseSeeder como orquestador unico** que llama a sub-seeders en orden estricto de dependencias. Eliminar fisicamente todos los seeders one-shot de debugging (no comentar, BORRAR).

**Rationale:** Comentar codigo muerto pudre. Los seeders de debugging son artefactos historicos sin valor para CI. La unica fuente de verdad de un setup E2E debe ser `DatabaseSeeder`.

**Rejected alternative:** Mantener sub-seeders condicionales con `if (app()->environment('local'))`. Rechazado: complica CI y mezcla responsabilidades de bootstrapping con debugging.

### 2.2 Seeders a ELIMINAR (delete fisico)

```
database/seeders/
  DeepDiagnosticAsesorSeeder.php
  CheckAllAsesorUsersSeeder.php
  FixAsesorUserAssociationSeeder.php
  FixPruebaAsesorSeeder.php
  TestNotificationSeeder.php
  AuditFase6PermisosSeeder.php
  AssignShieldPermissionsToAsesorSeeder.php
  TestAsesorPermissionsSeeder.php
  TestAsesorCreateClienteSeeder.php
```

**Pre-condicion:** `rg "FixPruebaAsesorSeeder|DeepDiagnostic..." --type php` debe arrojar cero referencias en codigo no-seeder antes de borrar.

### 2.3 Seeders a CREAR/REESCRIBIR

#### 2.3.1 `DatabaseSeeder` (orquestador)

```php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. Fundacionales (sin dependencias)
            PermissionSeeder::class,
            ProductoFinancieroSeeder::class,

            // 2. Usuarios y roles
            UsersSeeder::class,           // CREAR — Admin + JC + JO + Asesores demo

            // 3. Clientes y grupos
            ClientesGruposSeeder::class,  // CREAR — clientes demo + asignacion a grupos

            // 4. Prestamos y cuotas (canonico)
            PrestamosDesarrolloSeeder::class, // REESCRIBIR — usa cuota_individual

            // 5. Smoke tests opcionales (solo en local)
            // RetanqueoTestSeeder::class — solo si app()->environment('local')
        ]);
    }
}
```

#### 2.3.2 `UsersSeeder` (nuevo)

Firma:
```php
class UsersSeeder extends Seeder
{
    public function run(): void;
}
```

Crea:
- 1 usuario `admin@financiera.test` con rol `super_admin`.
- 1 usuario `jc@financiera.test` con rol `Jefe de creditos`.
- 1 usuario `jo@financiera.test` con rol `Jefe de operaciones`.
- 3 usuarios `asesor1..3@financiera.test` con rol `Asesor`.

Password universal en seeders: `password` (hashed via `Hash::make`).

#### 2.3.3 `ClientesGruposSeeder` (nuevo)

Crea ~15 clientes y los distribuye en 3 grupos de 5. Cada grupo asignado a un asesor.

#### 2.3.4 `PrestamosDesarrolloSeeder` (reescribir)

Crea 3 prestamos en distintos estados usando el sistema canonico:
- 1 `Pendiente` (sin cuotas).
- 1 `Activo` con `cuota_individual` generadas (5 cuotas, todas `pendiente`).
- 1 `Activo` con primera cuota `pagada` (con `aplicacion_pago` correspondiente).

Firma helper interno:
```php
private function crearPrestamoConCuotasIndividuales(
    Grupo $grupo,
    string $estado,
    int $cantidadCuotas,
): Prestamo;
```

#### 2.3.5 `TestWorkflowCompletoPrestamosSeeder` (reescribir)

Reemplazar referencias rotas:
- `Prestamo::ESTADO_POR_DESEMBOLSAR` → eliminar (no existe).
- `Prestamo::ESTADO_DESEMBOLSADO` → eliminar (no existe).
- Usar solo: `ESTADO_PENDIENTE`, `ESTADO_APROBADO`, `ESTADO_FIRMADO`, `ESTADO_ACTIVO`, `ESTADO_AL_DIA`, `ESTADO_EN_MORA`, `ESTADO_FINALIZADO`, `ESTADO_RECHAZADO`, `ESTADO_REFORMULADO`, `ESTADO_CANCELADO`.

Reemplazar metodos rotos en checks:
- `puedeDesembolsar()` → eliminar (concepto fuera del modelo nuevo).
- `puedeReducirMonto()` → mantener si existe en `Prestamo` post-Fase-3B; sino, eliminar el test.

**Importante:** este seeder es un smoke test interactivo — debe quedarse fuera de `DatabaseSeeder.run()` por defecto. Ejecucion explicita: `php artisan db:seed --class=TestWorkflowCompletoPrestamosSeeder`.

#### 2.3.6 `RetanqueoTestSeeder` (reescribir minimo)

Igual que arriba: actualizar referencias de estado y metodos. Si el seeder asume cuotas grupales, migrar a cuota_individual.

### 2.4 Edge cases

| Caso | Manejo |
|------|--------|
| `migrate:fresh --seed` en CI sin datos previos | Funciona: orden de seeders respeta FKs. |
| Re-ejecucion de seeders (idempotencia) | `PermissionSeeder` y `ProductoFinancieroSeeder` ya usan `updateOrCreate`. `UsersSeeder` debe usar `firstOrCreate` por email. |
| `RetanqueoTestSeeder` referencia ESTADO_DESEMBOLSADO | Reemplazar por `ESTADO_ACTIVO` (el equivalente moderno). |

### 2.5 Decision sobre transacciones

Cada sub-seeder se envuelve en `DB::transaction` interno. `DatabaseSeeder.run()` NO usa transaction global: si falla en seeder N, los previos quedan committed (esto es deseable para debugging incremental).

---

## 3. Eje 3 — GAP-01: Unlock canonical payment path

### 3.1 Decision tecnica

**Dos migraciones independientes** (`pagos`, `moras`) usando `change()` para permitir `cuota_grupal_id` nullable. **NO** se eliminan las FKs ni la columna: el legacy sigue siendo lectura, solo deja de bloquear writes canonicos.

**Rationale:** Drop fisico de `cuota_grupal_id` es out-of-scope del proposal y rompe lectores legacy (Filament Resources, reportes). Hacerla nullable es el minimo cambio que desbloquea el canonico sin tocar dependientes.

**Service layer:** **NO** se reescribe `PagoService` en este eje. Se agrega un metodo nuevo `PagoService::registrarCanonico()` que rutea al sistema canonico. El metodo `revertir()` legacy queda intacto. **Decision explicita: cohabitacion temporal de ambos paths.**

**Rejected alternative:** Migrar todo el flujo de pagos de una sola vez a canonico. Rechazado: explosion de scope, rompe Filament Resources existentes (PagoResource), exige tests E2E completos en este eje.

### 3.2 Migraciones

#### 3.2.1 `2026_04_29_100003_make_pagos_cuota_grupal_id_nullable.php`

```php
public function up(): void
{
    Schema::table('pagos', function (Blueprint $table) {
        $table->foreignId('cuota_grupal_id')
            ->nullable()
            ->change();
    });
}

public function down(): void
{
    // No-op: revertir a NOT NULL fallaría si hay registros con NULL
    // creados via canonical path. Documentado.
}
```

**Importante:** Laravel `change()` sobre `foreignId` requiere paquete `doctrine/dbal`. Verificar `composer.json` antes de ejecutar.

#### 3.2.2 `2026_04_29_100004_make_moras_cuota_grupal_id_nullable.php`

Identica estructura que 3.2.1, sobre tabla `moras`.

### 3.3 Cambios en modelos

#### 3.3.1 `app/Models/Pago.php`

```php
// Casts existentes + relacion canonica
public function aplicaciones(): HasMany
{
    return $this->hasMany(AplicacionPago::class, 'pago_id');
}

// Helper de routing
public function esCanonico(): bool
{
    return $this->cuota_grupal_id === null;
}
```

#### 3.3.2 `app/Models/Mora.php`

Identico patron: `cuota_grupal_id` nullable, helper `esCanonico()`.

### 3.4 Service layer — `PagoService::registrarCanonico()`

```php
namespace App\Services;

use App\Models\AplicacionPago;
use App\Models\CuotaIndividual;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;

class PagoService
{
    /**
     * Registra un pago via sistema canónico (cuota_individual + aplicacion_pago).
     * No usa cuota_grupal_id (queda NULL).
     *
     * @param CuotaIndividual $cuota   Cuota a la que se aplica
     * @param float           $monto   Monto total del pago
     * @param array           $aplicaciones  Distribución del monto:
     *                                       ['capital' => float, 'interes' => float, 'mora' => float]
     * @param string|null     $observaciones
     * @return Pago
     *
     * @throws \DomainException si la suma de aplicaciones != monto
     * @throws \DomainException si la cuota está cancelada o pagada
     */
    public function registrarCanonico(
        CuotaIndividual $cuota,
        float $monto,
        array $aplicaciones,
        ?string $observaciones = null,
    ): Pago;

    // ...método revertir() legacy intacto
}
```

**Validaciones internas (precondiciones):**
1. `$cuota->estado` ∈ {`pendiente`, `vencida`} — sino DomainException.
2. `array_sum($aplicaciones) === $monto` (con tolerancia 0.01 por floats) — sino DomainException.
3. Cada aplicacion individual `>= 0`.

**Pasos dentro de `DB::transaction`:**
1. Crear `Pago` con `cuota_grupal_id = null`, `tipo_pago = 'canonico'`, `estado_pago = 'aprobado'`.
2. Crear `AplicacionPago` con `pago_id`, `cuota_id = $cuota->id`, montos.
3. Decrementar `$cuota->saldo_capital` y `$cuota->saldo_interes` segun aplicaciones.
4. Si saldos llegan a 0, marcar `$cuota->estado = 'pagada'`.
5. `AuditService::registrar('pago_canonico_registrado', $pago, [], [...], $observaciones)`.

### 3.5 Edge cases

| Caso | Manejo |
|------|--------|
| Pago canonico con monto mayor al saldo | `DomainException` antes de crear el pago. |
| Cuota ya `pagada` recibe pago | `DomainException`. |
| Pago legacy creado por flujo viejo | Sigue funcionando; `cuota_grupal_id` NOT NULL en el row, `aplicaciones` vacio. |
| Test que crea Pago sin cuota_grupal_id en flujo legacy | Era imposible antes; ahora valido. Test debe distinguir paths con `Pago::esCanonico()`. |

### 3.6 Decision sobre transacciones

`registrarCanonico()` envuelve los 5 pasos en `DB::transaction(fn () => ...)`. AuditService DEBE invocarse dentro del closure. Si AuditService falla, rollback completo.

---

## 4. Eje 4 — GAP-02: SeparacionService

### 4.1 Decision tecnica

**Reescribir el service existente** `SeparacionClienteService` (no crear uno nuevo): agregar el flujo de creacion del prestamo individual de cobro. Renombrar el metodo principal de `separar()` a `separarClienteMoroso()` para alinear con la propuesta y dejar claro el dominio.

**Rationale:** `SeparacionClienteService` ya existe con la mitad del flujo (crea registro, audit, remueve cliente). Falta el paso clave: generar el `Prestamo` individual de cobro. Crear un service paralelo duplica logica de validacion.

**Rejected alternative:** Mantener `separar()` legacy y crear `separarConPrestamoNuevo()`. Rechazado: dos metodos haciendo casi lo mismo es deuda tecnica garantizada. Mejor evolucionar el existente.

**Producto financiero del nuevo prestamo:** `CI-EMPRENDEDOR` (codigo definido en `ProductoFinancieroSeeder`). Hardcodeado en el service via `ProductoFinanciero::where('codigo', 'CI-EMPRENDEDOR')->firstOrFail()`.

### 4.2 Migracion

#### 4.2.1 `2026_04_29_100005_add_prestamo_nuevo_id_to_separaciones_clientes.php`

```php
public function up(): void
{
    Schema::table('separaciones_clientes', function (Blueprint $table) {
        $table->foreignId('prestamo_nuevo_id')
            ->nullable()
            ->after('cliente_id')
            ->constrained('prestamos')
            ->nullOnDelete()
            ->comment('Préstamo individual de cobro generado al separar al cliente');

        $table->index('prestamo_nuevo_id', 'sep_prestamo_nuevo_index');
    });
}

public function down(): void
{
    Schema::table('separaciones_clientes', function (Blueprint $table) {
        $table->dropIndex('sep_prestamo_nuevo_index');
        $table->dropForeign(['prestamo_nuevo_id']);
        $table->dropColumn('prestamo_nuevo_id');
    });
}
```

### 4.3 Modelo `SeparacionCliente.php`

Agregar:
```php
protected $fillable = [
    // ...existentes
    'prestamo_nuevo_id',
];

public function prestamoNuevo(): BelongsTo
{
    return $this->belongsTo(Prestamo::class, 'prestamo_nuevo_id');
}
```

### 4.4 Service contract

```php
namespace App\Services;

use App\Models\Cliente;
use App\Models\Prestamo;
use App\Models\SeparacionCliente;
use App\Models\User;

class SeparacionClienteService
{
    /**
     * Separa un cliente moroso de un préstamo grupal.
     *
     * Flujo:
     *   1. Calcula deuda desde cuota_individual del préstamo origen.
     *   2. Crea préstamo individual de cobro (estado Activo, producto CI-EMPRENDEDOR).
     *   3. Crea cuota_individual única (numero_cuota=1, vencimiento +30 días).
     *   4. Crea registro de separación con prestamo_nuevo_id.
     *   5. Remueve cliente del grupo origen.
     *   6. Audita.
     *
     * Todo en DB::transaction.
     *
     * @param Prestamo $prestamoOrigen Préstamo grupal del que se separa
     * @param Cliente  $cliente        Cliente moroso a separar
     * @param User     $ejecutadoPor   Usuario que ejecuta (JC o JO)
     * @param string   $motivo         Justificación obligatoria
     * @param float    $penalizacion   Penalización opcional al grupo (default 0)
     * @return SeparacionCliente Con prestamo_nuevo_id ya enlazado
     *
     * @throws \DomainException Si el préstamo no es grupal
     * @throws \DomainException Si el cliente no pertenece al grupo
     * @throws \DomainException Si el cliente no tiene cuotas pendientes
     * @throws \DomainException Si el motivo está vacío
     */
    public function separarClienteMoroso(
        Prestamo $prestamoOrigen,
        Cliente $cliente,
        User $ejecutadoPor,
        string $motivo,
        float $penalizacion = 0,
    ): SeparacionCliente;

    // El método legacy `separar()` queda como alias deprecated:
    /** @deprecated 2026-04-29 — use separarClienteMoroso() */
    public function separar(
        Prestamo $prestamo,
        int $clienteId,
        string $motivo,
        float $penalizacion = 0,
    ): SeparacionCliente {
        return $this->separarClienteMoroso(
            $prestamo,
            Cliente::findOrFail($clienteId),
            auth()->user() ?? throw new \DomainException('User context required'),
            $motivo,
            $penalizacion,
        );
    }
}
```

### 4.5 Diagrama de secuencia

```
Caller (Controller/Action)
   |
   |-- separarClienteMoroso($prestamo, $cliente, $user, $motivo)
   v
SeparacionClienteService
   |
   |-- validatePrecondiciones()
   |     - prestamo.grupo != null
   |     - cliente in prestamo.grupo.clientes
   |     - exists cuota_individual where cliente_id and estado in (pendiente, vencida)
   |     - motivo not empty
   |
   v
DB::transaction
   |
   |-- 1. calcularDeuda()
   |     - sum(saldo_capital + saldo_interes) from cuota_individual
   |       where prestamo_id = $origen.id and cliente_id = $cliente.id
   |       and estado in (pendiente, vencida)
   |     - calcularDeudaMora() (existing helper)
   |
   |-- 2. ProductoFinanciero::where(codigo, 'CI-EMPRENDEDOR')->firstOrFail()
   |
   |-- 3. Prestamo::create([
   |       producto_id, cliente_id, grupo_id => null,
   |       monto_prestado_total => deudaCapital + deudaInteres,
   |       monto_devolver => idem,
   |       cantidad_cuotas => 1,
   |       fecha_prestamo => today,
   |       fecha_desembolso => today,
   |       frecuencia => 'unica',
   |       estado => Prestamo::ESTADO_ACTIVO,
   |       descripcion => "Cobro por separación de grupo {origen.id}",
   |     ])
   |
   |-- 4. CuotaIndividual::create([
   |       prestamo_id => $nuevoPrestamo.id,
   |       cliente_id => $cliente.id,
   |       numero_cuota => 1,
   |       monto_capital_original => deudaCapital,
   |       monto_interes_original => deudaInteres,
   |       saldo_capital => deudaCapital,
   |       saldo_interes => deudaInteres,
   |       fecha_vencimiento => today + 30 days,
   |       estado => 'pendiente',
   |     ])
   |
   |-- 5. SeparacionCliente::create([
   |       prestamo_origen_id, grupo_origen_id, cliente_id,
   |       prestamo_nuevo_id => $nuevoPrestamo.id,    <-- LO NUEVO
   |       ejecutado_por => $user.id,
   |       deuda_capital, deuda_mora, penalizacion_grupo, motivo,
   |       estado => 'ejecutada',
   |     ])
   |
   |-- 6. $prestamoOrigen.grupo.removerCliente($cliente.id)
   |
   |-- 7. AuditService::registrar(
   |       'separacion_creada',
   |       $separacion,                <-- modelo principal
   |       [estado_grupo: prestamoOrigen.grupo.estado_grupo,
   |        integrantes_origen: count],
   |       [prestamo_nuevo_id, deuda_capital, deuda_mora, penalizacion],
   |       $motivo
   |     )
   |
   v
return $separacion (con prestamo_nuevo_id cargado)
```

### 4.6 Edge cases

| Caso | Manejo |
|------|--------|
| Cliente sin cuota_individual pendiente | `DomainException`: "El cliente no tiene deuda pendiente para separar." Antes de transaccion. |
| Producto `CI-EMPRENDEDOR` no existe | `ModelNotFoundException` (firstOrFail) — falla loud. Pre-condicion documentada: ProductoFinancieroSeeder debe correr antes. |
| `auth()->id()` es null (consola, tests) | El service recibe `User $ejecutadoPor` explicito; no depende de `auth()`. El alias deprecated falla con DomainException si no hay user en contexto. |
| Deuda total = 0 (cliente al dia, llamada erronea) | `DomainException`: "El cliente no tiene deuda; verifica si corresponde separar." Validacion al inicio. |
| Cliente ya separado previamente (separacion existe en estado 'ejecutada') | `DomainException`: "El cliente ya fue separado del grupo." Query previa. |
| Grupo queda con < 3 integrantes despues | Out of scope: el service no valida tamano minimo del grupo. Eso es politica del Resource/Action. |
| Falla al crear cuota_individual del nuevo prestamo | Rollback completo via transaction; nada se persiste. |

### 4.7 Decision sobre transacciones

Todo el flujo (pasos 1-7) DENTRO de `DB::transaction`. AuditService es el ultimo paso, dentro de la transaccion. Si audit falla, rollback completo de la separacion (decision: auditoria es parte del contrato, no efecto secundario).

---

## 5. Eje 5 — GAP-04: Reagrupaciones JSON → pivot

### 5.1 Decision tecnica

**Migracion en TRES pasos separados** para garantizar rollback intermedio:

1. Crear tabla pivot `reagrupacion_cliente`.
2. Migrar datos: leer JSON existente y poblar pivot.
3. Drop columnas JSON.

**Rationale:** Drop de JSON antes de migrar datos = perdida irreversible. Hacer todo en una sola migracion impide testear migracion de datos en isolation. Tres migraciones separadas permiten testear cada paso, validar conteos y abortar si la data se corrompe.

**Rejected alternative:** Migracion en dos pasos (crear+migrar en una, drop en otra). Rechazado: si crear+migrar falla a mitad, dejas tabla pivot huerfana sin datos completos. Tres pasos da granularidad maxima.

### 5.2 Migraciones

#### 5.2.1 `2026_04_29_100006_create_reagrupacion_cliente_table.php`

```php
public function up(): void
{
    Schema::create('reagrupacion_cliente', function (Blueprint $table) {
        $table->id();
        $table->foreignId('reagrupacion_id')
            ->constrained('reagrupaciones')
            ->cascadeOnDelete();
        $table->foreignId('cliente_id')
            ->constrained('clientes')
            ->restrictOnDelete();
        $table->enum('tipo', ['trasladado', 'retenido'])
            ->comment('trasladado=va al nuevo grupo; retenido=queda en el origen');
        $table->timestamps();

        $table->unique(['reagrupacion_id', 'cliente_id'], 'reag_cli_unique');
        $table->index(['reagrupacion_id', 'tipo'], 'reag_cli_tipo_index');
    });
}

public function down(): void
{
    Schema::dropIfExists('reagrupacion_cliente');
}
```

#### 5.2.2 `2026_04_29_100007_migrate_reagrupaciones_json_to_pivot.php`

```php
public function up(): void
{
    $reagrupaciones = DB::table('reagrupaciones')->get(['id', 'clientes_trasladados', 'clientes_retenidos']);

    foreach ($reagrupaciones as $reag) {
        $trasladados = json_decode($reag->clientes_trasladados ?? '[]', true) ?: [];
        $retenidos   = json_decode($reag->clientes_retenidos ?? '[]', true) ?: [];

        foreach ($trasladados as $clienteId) {
            DB::table('reagrupacion_cliente')->insertOrIgnore([
                'reagrupacion_id' => $reag->id,
                'cliente_id'      => $clienteId,
                'tipo'            => 'trasladado',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        foreach ($retenidos as $clienteId) {
            DB::table('reagrupacion_cliente')->insertOrIgnore([
                'reagrupacion_id' => $reag->id,
                'cliente_id'      => $clienteId,
                'tipo'            => 'retenido',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }
}

public function down(): void
{
    // Idempotente: borra solo las filas que fueron migradas.
    DB::table('reagrupacion_cliente')->truncate();
}
```

#### 5.2.3 `2026_04_29_100008_drop_json_columns_from_reagrupaciones.php`

```php
public function up(): void
{
    Schema::table('reagrupaciones', function (Blueprint $table) {
        $table->dropColumn(['clientes_trasladados', 'clientes_retenidos']);
    });
}

public function down(): void
{
    Schema::table('reagrupaciones', function (Blueprint $table) {
        $table->json('clientes_trasladados')->nullable();
        $table->json('clientes_retenidos')->nullable();
    });
    // Nota: el down NO restaura los datos JSON; los datos viven solo en pivot
    // a partir de la migracion 5.2.2. Documentado.
}
```

### 5.3 Cambios en `app/Models/Reagrupacion.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Reagrupacion extends Model
{
    protected $fillable = [
        'grupo_origen_id', 'grupo_nuevo_id', 'prestamo_origen_id',
        'ejecutado_por', 'tipo', 'monto_descuento', 'observaciones',
        // 'clientes_trasladados' y 'clientes_retenidos' eliminados
    ];

    // Cast JSON eliminado

    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class, 'reagrupacion_cliente')
            ->withPivot('tipo')
            ->withTimestamps();
    }

    public function clientesTrasladados(): BelongsToMany
    {
        return $this->clientes()->wherePivot('tipo', 'trasladado');
    }

    public function clientesRetenidos(): BelongsToMany
    {
        return $this->clientes()->wherePivot('tipo', 'retenido');
    }
}
```

### 5.4 Cambios en `app/Services/ReagrupacionParcialService.php`

Reemplazar el `Reagrupacion::create([...clientes_trasladados, clientes_retenidos...])` por:

```php
return DB::transaction(function () use (...) {
    // ...pasos 1-3 igual

    $reagrupacion = Reagrupacion::create([
        'grupo_origen_id'   => $grupoOrigen->id,
        'grupo_nuevo_id'    => $grupoNuevo->id,
        'prestamo_origen_id' => $prestamo->id,
        'ejecutado_por'     => auth()->id(),
        'tipo'              => $tipo,
        'monto_descuento'   => $montoDescuento,
        'observaciones'     => $observaciones,
    ]);

    // Pivot: attach con tipo via array de pares
    $pivotData = [];
    foreach ($clientesTrasladados as $cId) {
        $pivotData[$cId] = ['tipo' => 'trasladado'];
    }
    foreach ($clientesRetenidos as $cId) {
        $pivotData[$cId] = ['tipo' => 'retenido'];
    }
    $reagrupacion->clientes()->attach($pivotData);

    // ...auditoria igual, leyendo $reagrupacion->clientesTrasladados()->count()
});
```

### 5.5 Edge cases

| Caso | Manejo |
|------|--------|
| Reagrupacion existente con JSON `null` o `[]` | Migracion 5.2.2 itera sobre `?: []` — no inserta nada. OK. |
| JSON malformado en BD | `json_decode` retorna `null`, fallback a `[]`. Log warning si quieres ser defensivo. |
| Cliente ID en JSON que ya no existe en `clientes` | FK constraint en migracion 5.2.1 falla en INSERT. **Mitigacion:** correr query previa que detecte clientes huerfanos antes de migrar; abortar si hay. |
| Test que asume `$reagrupacion->clientes_trasladados` (atributo) | Falla — debe migrarse a `$reagrupacion->clientesTrasladados` (relacion). Cambio breaking documentado. |
| Filament Resource que lee JSON | Out of scope si existe; documentar warnings al ejecutar. |

### 5.6 Decision sobre transacciones

Las TRES migraciones NO se envuelven en transaccion explicita conjunta — cada una es atomica de Laravel. La de migracion de datos (5.2.2) usa `insertOrIgnore` para idempotencia. Si falla a mitad, rollback automatico de Laravel revierte solo esa migracion.

`ReagrupacionParcialService.reagrupar()` SIGUE usando `DB::transaction` porque el flujo crea Grupo + transferencias + Reagrupacion + Pivot en multi-write.

---

## 6. ADRs (Architectural Decision Records)

### ADR-001: No drop fisico de `cuotas_grupales`, `pagos.cuota_grupal_id`, `moras.cuota_grupal_id`

**Decision:** Solo se hace `cuota_grupal_id` nullable. La columna y la tabla `cuotas_grupales` permanecen.

**Rationale:** Drop fisico requiere refactor en cascada de Filament Resources (`PagoResource`, `CuotaGrupalResource`, etc.), reportes, dashboard widgets y service legacy `RetanqueoService`. Out-of-scope del proposal.

**Rejected:** Drop completo del legacy. Habria sido cleaner pero explota el scope x10.

**Consecuencia:** Convivencia temporal de dos paths (legacy lectura + canonico writes nuevos). Aceptable.

### ADR-002: Migraciones en multiples archivos por eje

**Decision:** Cada cambio DDL/DML va en archivo de migracion separado.

**Rationale:** Granularidad de rollback, testabilidad aislada, claridad en `migrations` table.

**Rejected:** Una migracion mega por eje. Mas rapido de escribir pero rollback all-or-nothing.

**Consecuencia:** ~12-14 migraciones nuevas en este change. Aceptable.

### ADR-003: SeparacionService recibe `User` explicito (no `auth()`)

**Decision:** `separarClienteMoroso(..., User $ejecutadoPor, ...)` en lugar de leer `auth()->user()` internamente.

**Rationale:** Testabilidad (no requiere `actingAs`), explicitness, ejecuciones desde consola/seeders viables.

**Rejected:** Leer `auth()` adentro. Mas conveniente pero acopla a HTTP context.

**Consecuencia:** Callers (Filament Actions, Controllers) deben pasar `auth()->user()` explicitamente.

### ADR-004: Producto financiero del prestamo de cobro hardcodeado a `CI-EMPRENDEDOR`

**Decision:** El nuevo prestamo individual generado por separacion usa siempre `producto_id` de `ProductoFinanciero` con `codigo='CI-EMPRENDEDOR'`.

**Rationale:** Cualquier producto individual sirve mientras tenga tasa razonable; el codigo CI-EMPRENDEDOR ya existe y es el unico individual seedado. Evita parametrizacion prematura.

**Rejected:** Crear un nuevo producto `CI-COBRO-SEPARACION`. Out-of-scope. Si en el futuro se necesita diferenciar, se agrega y se cambia el lookup.

**Consecuencia:** Si `CI-EMPRENDEDOR` cambia tasas, afecta cobros por separacion. Aceptable: misma logica que cualquier prestamo.

### ADR-005: AuditLog dentro de la transaccion

**Decision:** `AuditService::registrar()` se invoca DENTRO del `DB::transaction` closure de cada operacion auditable.

**Rationale:** Garantiza atomicidad: si la accion principal falla, no queda registro audit huerfano. Si audit falla, la accion completa rollback.

**Rejected:** AuditService como event listener async post-commit. Mas resiliente pero pierde garantia de atomicidad.

**Consecuencia:** Performance: cada transaccion lleva 1 INSERT extra. Aceptable.

### ADR-006: Pivot con `tipo` enum vs dos tablas pivot separadas

**Decision:** Una sola tabla `reagrupacion_cliente` con columna `tipo ENUM('trasladado','retenido')`.

**Rationale:** Mismo schema base (reagrupacion_id + cliente_id), unica diferencia es semantica del rol. Una tabla con discriminador es DRY. Queries via `wherePivot('tipo', 'trasladado')`.

**Rejected:** Dos tablas `reagrupacion_cliente_trasladado` y `reagrupacion_cliente_retenido`. Duplicacion sin valor.

**Consecuencia:** Queries cross-tipo son triviales (`->clientes()`); queries por-tipo requieren pivot filter. OK.

---

## 7. Resumen de archivos afectados

### Migraciones nuevas (8 archivos)

```
database/migrations/
  2026_04_29_100001_normalize_prestamo_individual_estado_data.php       (Eje 1)
  2026_04_29_100002_change_prestamo_individual_estado_to_string.php     (Eje 1)
  2026_04_29_100003_make_pagos_cuota_grupal_id_nullable.php             (Eje 3)
  2026_04_29_100004_make_moras_cuota_grupal_id_nullable.php             (Eje 3)
  2026_04_29_100005_add_prestamo_nuevo_id_to_separaciones_clientes.php  (Eje 4)
  2026_04_29_100006_create_reagrupacion_cliente_table.php               (Eje 5)
  2026_04_29_100007_migrate_reagrupaciones_json_to_pivot.php            (Eje 5)
  2026_04_29_100008_drop_json_columns_from_reagrupaciones.php           (Eje 5)
```

### Modelos modificados (4)

```
app/Models/PrestamoIndividual.php   (Eje 1: cast estado, constantes)
app/Models/Pago.php                 (Eje 3: relacion aplicaciones, helper)
app/Models/Mora.php                 (Eje 3: helper canonico)
app/Models/SeparacionCliente.php    (Eje 4: prestamoNuevo relation)
app/Models/Reagrupacion.php         (Eje 5: belongsToMany, fillable)
```

### Services modificados/creados (3)

```
app/Services/PagoService.php              (Eje 3: + registrarCanonico())
app/Services/SeparacionClienteService.php (Eje 4: separarClienteMoroso(), separar() deprecated)
app/Services/ReagrupacionParcialService.php (Eje 5: attach pivot)
```

### Seeders (delete + reescribir)

```
ELIMINAR:
  database/seeders/DeepDiagnosticAsesorSeeder.php
  database/seeders/CheckAllAsesorUsersSeeder.php
  database/seeders/FixAsesorUserAssociationSeeder.php
  database/seeders/FixPruebaAsesorSeeder.php
  database/seeders/TestNotificationSeeder.php
  database/seeders/AuditFase6PermisosSeeder.php
  database/seeders/AssignShieldPermissionsToAsesorSeeder.php
  database/seeders/TestAsesorPermissionsSeeder.php
  database/seeders/TestAsesorCreateClienteSeeder.php

CREAR:
  database/seeders/UsersSeeder.php
  database/seeders/ClientesGruposSeeder.php
  database/seeders/PrestamosDesarrolloSeeder.php

REESCRIBIR:
  database/seeders/DatabaseSeeder.php
  database/seeders/TestWorkflowCompletoPrestamosSeeder.php
  database/seeders/RetanqueoTestSeeder.php
```

### Tests Pest (uno por eje minimo)

```
tests/Feature/PrestamoIndividualEstadoTest.php       (Eje 1)
tests/Feature/SeedersIntegridadTest.php              (Eje 2)
tests/Feature/PagoCanonicoTest.php                   (Eje 3)
tests/Feature/SeparacionServiceTest.php              (Eje 4)
tests/Feature/ReagrupacionPivotTest.php              (Eje 5)
```

---

## 8. Riesgos arquitecturales

| Riesgo | Severidad | Mitigacion |
|--------|-----------|------------|
| `change()` requiere `doctrine/dbal` no instalado | Alta | Verificar `composer.json` antes de Eje 1 y Eje 3; instalar si falta. |
| Datos JSON corruptos en `reagrupaciones` | Media | Migracion 5.2.2 hace `?? '[]'` y `insertOrIgnore`; query previa de huerfanos. |
| `RetanqueoService` legacy escribe en `cuotas_grupales` | Baja | Out of scope; sigue funcionando con FKs ahora nullable. |
| Tests existentes asumen `cuota_grupal_id NOT NULL` | Media | Eje 3 incluye sweep de tests; actualizar mocks. |
| AuditService falla y rompe operacion legitima | Baja | ADR-005 explicito: rollback es deseable. |
| Producto `CI-EMPRENDEDOR` no esta seedeado en CI | Alta | DatabaseSeeder garantiza `ProductoFinancieroSeeder` antes de cualquier seeder de prestamos. |
| Tablas `cuotas_grupales` legacy referenciadas por Filament | Media | No se elimina la tabla; lectores siguen vivos. |
| `ProductoFinanciero` lookup hardcodeado por codigo string | Baja | Test en Eje 4 valida que el producto existe; falla loud si no. |

---

## 9. Definition of Done arquitectural

Un eje esta "designed-complete" cuando:

1. Migraciones declaradas con nombre, columnas, tipos e indices.
2. Contratos de service/modelo declarados con firmas tipadas.
3. Edge cases mapeados con manejo explicito.
4. Decisiones de transaccion documentadas.
5. Tests Pest planificados (no implementados — eso es `sdd-tasks`/`sdd-apply`).
6. ADRs registrados para decisiones controvertibles.

Los 5 ejes cumplen estos criterios.

---

## 10. Handoff a `sdd-tasks`

Este design es input para `sdd-tasks`. La fase de tasks debe producir:

- Breakdown granular por eje (cada eje → 4-8 tasks).
- Orden de ejecucion respetando dependencias (E1 → E2 → E3 → E4 → E5).
- Tests Pest red-first como primer task de cada eje (Strict TDD).
- Commit boundaries sugeridos (1 commit por archivo de migracion + 1 por test + 1 por implementacion).

NO entra en este design: el detalle de cada test, los pasos exactos de cada commit, los Filament Resources tocados.
