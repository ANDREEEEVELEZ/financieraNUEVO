# Proposal: modelo-financiero-v2

**Status:** Proposed
**Date:** 2026-04-29
**Author:** Pier (via SDD orchestrator)
**Artifact Store:** hybrid (engram + openspec)

---

## 1. Intent

### Problem

El modelo financiero del sistema convive con **dos esquemas de cuotas y pagos en paralelo** sin un puente formal entre ellos:

- **Sistema legacy** (`cuotas_grupales`, `pagos.cuota_grupal_id`, `moras.cuota_grupal_id`) — sigue siendo obligatorio a nivel de schema porque las FKs son `NOT NULL`.
- **Sistema canonico** (`cuota_individual` + `aplicacion_pago` + `ajuste_deuda`) — esta arquitectonicamente completo pero **no puede ser usado** sin romper el constraint legacy.

Como consecuencia directa de esa coexistencia (y de gaps acumulados durante migraciones previas), el sistema tiene:

1. Un **bug silencioso de corrupcion de datos**: `prestamo_individual.estado` esta tipado como `decimal` y absorbe escrituras de strings (`'Aprobado'`, `'Firmado'`...) como `0.00` sin error.
2. **Seeders rotos** que referencian constantes eliminadas (`ESTADO_POR_DESEMBOLSAR`, `ESTADO_DESEMBOLSADO`) — provocan fatal error si se corren en CI.
3. Un **flujo de negocio incompleto**: cuando un grupo separa a un moroso, la tabla `separaciones_clientes` registra el snapshot de deuda pero **no enlaza al nuevo prestamo individual** que deberia generarse para que ese cliente siga siendo cobrable.
4. **Datos relacionales guardados como JSON** (`reagrupaciones.clientes_trasladados`, `clientes_retenidos`) sin integridad referencial, sin posibilidad de JOIN, sin FK.

### Why now

- Estamos en **entorno de desarrollo, sin datos reales en produccion** — la ventana para romper compatibilidad legacy y dejar el modelo limpio se cierra apenas se cargue data productiva.
- La Fase 3A/3B ya elimino estados y aliases legacy a nivel de modelo; el siguiente paso natural es alinear el **schema** con el **dominio canonico**.
- El bug de `decimal estado` corrompe datos cada vez que un test o flujo manual transita estados de prestamo individual. Cada dia que pasa se acumula corrupcion silenciosa en BDs locales.
- Los seeders rotos bloquean cualquier intento de poblar el sistema con escenarios reales para el resto de SDD changes que vengan despues.

### Success looks like

- `pagos` y `moras` pueden vivir **exclusivamente** sobre `aplicacion_pago` + `cuota_individual`. `cuota_grupal_id` queda como columna nullable de compatibilidad historica, sin escritura nueva.
- `prestamo_individual.estado` es un `string(50)` consistente con el state machine de `Prestamo`. Cero escrituras silenciosas a `0.00`.
- Separar un cliente moroso es **un solo metodo de service** que crea el `Prestamo` individual derivado, su `cuota_individual` con la deuda calculada, actualiza `separaciones_clientes.prestamo_nuevo_id` y registra todo en `audit_logs` — todo en una transaccion atomica.
- Las reagrupaciones tienen integridad referencial real via tabla pivot `reagrupacion_cliente` con FKs a `clientes` y enum `tipo` (`trasladado` | `retenido`).
- `php artisan db:seed` ejecuta sin errores y deja un dataset de desarrollo coherente y reproducible. Los 8+ seeders de diagnostico/parche estan eliminados.
- La suite Pest pasa al 100% con `php artisan test` despues de cada eje.

---

## 2. Scope

### In-scope

- Migracion incremental para tipo de columna `prestamo_individual.estado` (`decimal` -> `string(50)`).
- Migraciones incrementales `change()` para hacer `pagos.cuota_grupal_id` y `moras.cuota_grupal_id` nullable.
- Verificacion y ajuste del **service layer** que persiste pagos para que el flujo canonico (`aplicacion_pago` -> `cuota_individual`) sea el unico camino de escritura.
- Migracion incremental para agregar `separaciones_clientes.prestamo_nuevo_id` (nullable, FK a `prestamos`).
- Nuevo `app/Services/SeparacionService.php` con metodo `separarClienteMoroso(Prestamo $origen, Cliente $cliente, User $ejecutor, string $motivo): Prestamo` — atomico, con `audit_logs`.
- Nueva tabla `reagrupacion_cliente` (pivot con `tipo` enum).
- Migracion de datos JSON -> filas pivot, seguida de migracion que elimina las columnas JSON.
- Limpieza de seeders: eliminar 8+ obsoletos, reescribir `DatabaseSeeder` orquestador, crear `PrestamosDesarrolloSeeder` que use el sistema canonico, reescribir `TestWorkflowCompletoPrestamosSeeder` y `RetanqueoTestSeeder`.
- Tests Pest por cada eje (Strict TDD: red -> green -> refactor).

### Out-of-scope

- Eliminar fisicamente la tabla `cuotas_grupales` ni la relacion `cuotasGrupales()` del modelo `Prestamo` — se preservan como **legado pasivo** (compatibilidad de lectura para reportes/historicos).
- Migrar datos historicos del sistema legacy al canonico (no hay datos historicos: entorno de desarrollo).
- Cambios en Filament Resources mas alla de los necesarios para que el flujo no se rompa (no es un rediseno UI).
- Refactor del servicio de retanqueo (`RetanqueoService`) salvo lo estrictamente necesario para no romper su test suite.
- Cambios en el modelo de permisos / Filament Shield.
- Reescritura del modelo de moras (solo se hace nullable la FK; la logica de calculo queda como esta).

---

## 3. Approach

**Estrategia general: Incremental por eje (Approach B de la exploracion).**

Cada eje es una unidad shippable independiente, con su propia migracion, sus propios tests Pest, y sin dependencias circulares con los siguientes. El orden esta diseniado para que cada eje **desbloquee** al siguiente sin requerir rollback.

### Rationale del orden

1. **GAP-03 primero (bug fix puro):** es la unica accion sin dependencias y elimina corrupcion activa. Es la red de seguridad: cualquier eje posterior que toque estados va a beneficiarse de esto.
2. **GAP-05 segundo (seeders):** sin seeders funcionales no podemos validar de punta a punta los ejes siguientes. Reconstruirlos temprano da un dataset de prueba reproducible.
3. **GAP-01 tercero (schema unlock):** hacer nullables las FKs legacy es la operacion de mas alto impacto arquitectonico. Una vez aplicada, el sistema canonico esta libre.
4. **GAP-02 cuarto (SeparacionService):** depende de tener el sistema canonico operativo (paso anterior) porque el nuevo prestamo necesita su `cuota_individual` calculada desde el sistema canonico, no desde `cuotas_grupales`.
5. **GAP-04 ultimo (normalizacion JSON):** es el unico que requiere migracion de datos. Se hace al final cuando el resto del flujo es estable, minimizando el riesgo de tener que rehacer la migracion de datos.

### Tecnicas transversales

- **Migraciones incrementales** — nunca `migrate:reset`. Cada migracion tiene `up()` y `down()` reales y verificados.
- **Strict TDD** — cada cambio empieza por un test Pest que falle (red), implementacion minima (green), refactor.
- **Conventional commits** — un commit por eje al menos, con prefijos `fix:`, `feat:`, `refactor:`, `chore:`.
- **Audit logs polimorficos** — toda operacion de dominio (separacion, reagrupacion) se registra en `audit_logs`.
- **Zero IDE warnings** — strict return types, enums/constantes, sin nullables ambiguos.

---

## 4. Axes (5 working axes)

### Axis 1 — GAP-03: Fix `prestamo_individual.estado` column type

**Objetivo:** convertir la columna de `decimal` a `string(50)` y normalizar cualquier valor existente (`0.00` / `NULL`) a un estado consistente con el state machine de `Prestamo`.

**Archivos afectados:**
- Nueva migracion `XXXX_fix_prestamo_individual_estado_column.php`
- `app/Models/PrestamoIndividual.php` — cast explicito si aplica
- Tests Pest: `tests/Feature/PrestamoIndividualEstadoTest.php`

**Criterio de cierre:** un test que escribe `'Aprobado'` y lo lee de vuelta como `'Aprobado'` (no `0.00`).

---

### Axis 2 — GAP-05: Seeders rebuild

**Objetivo:** dejar `php artisan db:seed` funcional y reproducible, alineado al sistema canonico.

**Acciones:**
- Eliminar: `FixPruebaAsesorSeeder`, `DeepDiagnosticAsesorSeeder`, `CheckAllAsesorUsersSeeder`, `FixAsesorUserAssociationSeeder`, `TestNotificationSeeder`, `AuditFase6PermisosSeeder`, `AssignShieldPermissionsToAsesorSeeder`, `TestAsesorPermissionsSeeder`, `TestAsesorCreateClienteSeeder`.
- Reescribir `DatabaseSeeder` como orquestador maestro.
- Reescribir `TestWorkflowCompletoPrestamosSeeder` usando estados vigentes (`ESTADO_PENDIENTE`, `ESTADO_APROBADO`, `ESTADO_FIRMADO`, `ESTADO_ACTIVO`...) y relacion `cuotasIndividuales()`.
- Crear `PrestamosDesarrolloSeeder` que produzca un dataset realista (grupo + clientes + prestamo + cuotas individuales).
- Reescribir `RetanqueoTestSeeder` sobre el sistema canonico.

**Criterio de cierre:** `php artisan migrate:fresh --seed` corre en limpio y deja BD lista para los tests Pest.

---

### Axis 3 — GAP-01: Unlock canonical payment path

**Objetivo:** hacer nullable las FKs legacy y garantizar que el service layer rutea pagos exclusivamente al sistema canonico.

**Archivos afectados:**
- Nueva migracion `XXXX_make_pagos_cuota_grupal_nullable.php`
- Nueva migracion `XXXX_make_moras_cuota_grupal_nullable.php`
- Verificar/actualizar service layer de pagos (probablemente en un `PagoService` nuevo o extension del controller actual).
- `app/Models/Pago.php`, `app/Models/Mora.php` — cast/relacion nullable.
- Tests Pest: pago canonico crea `Pago` + `AplicacionPago` sin tocar `cuotas_grupales`.

**Criterio de cierre:** se puede crear un `Pago` sin `cuota_grupal_id` y queda persistido con su `aplicacion_pago` correspondiente.

---

### Axis 4 — GAP-02: SeparacionService

**Objetivo:** automatizar la separacion de un cliente moroso. La separacion crea automaticamente el nuevo prestamo individual y queda enlazada via FK.

**Archivos afectados:**
- Nueva migracion `XXXX_add_prestamo_nuevo_to_separaciones_clientes.php`
- Nuevo `app/Services/SeparacionService.php` con firma:
  `separarClienteMoroso(Prestamo $origen, Cliente $cliente, User $ejecutor, string $motivo): Prestamo`
- Tests Pest: separar un moroso crea Prestamo individual con `cuota_individual` por la deuda exacta, actualiza `separaciones_clientes.prestamo_nuevo_id`, escribe `audit_logs` con metadata.

**Comportamiento del service (transaccion atomica):**
1. Calcular deuda total del cliente desde `cuota_individual` del prestamo origen.
2. Crear `Prestamo` individual con producto financiero, asesor, monto = deuda calculada, estado `Activo`.
3. Crear `cuota_individual` del nuevo prestamo (saldo_capital + saldo_interes derivados).
4. Actualizar `separaciones_clientes.prestamo_nuevo_id`.
5. Registrar `audit_logs` polimorfico (`auditable_type` = `Prestamo`, accion = `separacion_creada`).

**Criterio de cierre:** test E2E donde se llama `separarClienteMoroso(...)` y se verifica el chain completo en BD.

---

### Axis 5 — GAP-04: Reagrupaciones JSON -> pivot

**Objetivo:** normalizar `reagrupaciones.clientes_trasladados` y `clientes_retenidos` (JSON) a tabla pivot con FKs.

**Archivos afectados:**
- Nueva migracion `XXXX_create_reagrupacion_cliente_table.php` (estructura: `reagrupacion_id`, `cliente_id`, `tipo` enum).
- Nueva migracion `XXXX_migrate_reagrupaciones_json_to_pivot.php` (migra datos existentes, si los hay).
- Nueva migracion `XXXX_remove_json_columns_from_reagrupaciones.php` (drop columnas JSON despues de migrar datos).
- `app/Models/Reagrupacion.php` — relacion `belongsToMany(Cliente::class)->withPivot('tipo')`.
- Tests Pest: query reagrupaciones por tipo trasladado / retenido devuelve lista correcta de Cliente models.

**Criterio de cierre:** las columnas JSON ya no existen y todas las consultas usan la tabla pivot. JOIN funciona y la integridad referencial se aplica.

---

## 5. Risks & Mitigations

| Riesgo | Severidad | Mitigacion |
|--------|-----------|------------|
| Migracion `decimal` -> `string` puede dejar valores `0.00` o `NULL` que no mapean a un estado valido | Media | La migracion incluye `update()` que convierte explicitamente: `0.00`/`NULL` -> `Pendiente` (estado seguro por defecto) antes del cambio de tipo. |
| Hacer nullable `cuota_grupal_id` no garantiza que el service layer rutee al canonico — codigo viejo podria seguir escribiendo legacy | Alta | El eje incluye verificacion explicita del service layer y test que falla si se escribe en `cuotas_grupales` desde el flujo de pago nuevo. |
| `SeparacionService` calcula deuda desde `cuota_individual` — si el prestamo origen no tiene cuotas individuales todavia (caso edge), falla | Media | El service valida precondicion (prestamo origen tiene `cuota_individual`) y arroja `DomainException` con mensaje claro si no la tiene. |
| Migracion JSON -> pivot puede perder datos si el JSON tiene formato inesperado | Media | Migracion en dos pasos: primero crear pivot y migrar (con dry-run en log), recien despues drop columnas JSON en migracion separada. Permite rollback intermedio. |
| Eliminar 8+ seeders puede romper algo no documentado que dependa de ellos | Baja | Antes de eliminar, `Grep` por referencias a cada nombre de clase. Si hay referencia, se gestiona en el mismo eje. |
| Strict TDD aumenta tiempo por eje | Baja (aceptado) | Es un tradeoff conciente del proyecto. La calidad y reproducibilidad lo justifica. |
| Tests Pest existentes podrian romperse al hacer nullable las FKs si asumen NOT NULL | Media | Antes de cada migracion, correr suite y mapear tests que dependen del schema viejo. Actualizar en el mismo PR. |

---

## 6. Success Criteria

El change esta hecho cuando:

1. `php artisan test` pasa al 100% despues de cada eje, sin tests skippeados y sin warnings de schema.
2. `php artisan migrate:fresh --seed` corre limpio y deja un dataset de desarrollo poblado y consistente.
3. No queda ninguna referencia activa a `ESTADO_POR_DESEMBOLSAR` o `ESTADO_DESEMBOLSADO` en seeders, tests o codigo de aplicacion.
4. Crear un `Pago` sin `cuota_grupal_id` funciona y queda persistido en el flujo canonico (`aplicacion_pago` + `cuota_individual`).
5. `prestamo_individual.estado` puede contener cualquier estado del state machine de `Prestamo` y se persiste correctamente — verificado por test.
6. Llamar `SeparacionService::separarClienteMoroso(...)` deja BD en estado consistente: `separaciones_clientes` con `prestamo_nuevo_id` poblado, nuevo `Prestamo` con su `cuota_individual` correspondiente, y `audit_logs` con la metadata.
7. `Reagrupacion::clientesTrasladados()` y `clientesRetenidos()` devuelven `Collection<Cliente>` desde la tabla pivot, no desde JSON.
8. Cero columnas JSON en `reagrupaciones`. Cero IDE warnings en los modelos tocados.
9. Cada eje tiene al menos un commit conventional con mensaje descriptivo y sin atribucion AI.

---

## 7. Out of Scope (limites explicitos)

Para evitar scope creep, este change **NO** incluye:

- Eliminacion fisica de la tabla `cuotas_grupales` o de la relacion `cuotasGrupales()` en `Prestamo`. Se mantienen como legado pasivo de solo lectura.
- Migracion de datos productivos (no aplica: entorno de desarrollo).
- Rediseno o refactor de Filament Resources (`PrestamoResource`, `PagoResource`, etc.) mas alla de ajustes minimos para que el flujo no se rompa.
- Refactor de `RetanqueoService` y su flujo de retanqueo (solo se ajusta lo necesario para no romper sus tests existentes).
- Cambios en el sistema de permisos, roles, Filament Shield o policies.
- Cambios en notifications, dashboards o widgets.
- Cambios en el modelo de moras a nivel de calculo de intereses; solo se hace nullable la FK al sistema legacy.
- Optimizaciones de performance (indices nuevos, eager loading, etc.) que no esten directamente requeridas por los ejes.
- Internacionalizacion / cambios de idioma en strings de UI.

---

## 8. Next Phases

Este proposal habilita ejecutar en paralelo:

- **`sdd-spec`**: especificacion formal de comportamiento por eje (qué debe pasar en cada caso, criterios de aceptacion testables).
- **`sdd-design`**: decisiones arquitecturales detalladas por eje (forma exacta de migraciones, contratos de service, transacciones).

Despues de spec + design, `sdd-tasks` desglosa en subtareas mecanicas para `sdd-apply`.
