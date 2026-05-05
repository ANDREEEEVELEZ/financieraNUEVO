# Spec: modelo-financiero-v2

**Status:** Draft | **Date:** 2026-04-29 | **RFC:** 2119

---

## Axis 1 — GAP-03: PrestamoIndividual.estado type fix

### Requirement: Estado column MUST be string(50)

The `prestamo_individual.estado` column MUST be of type `VARCHAR(50)`. It MUST NOT be `decimal` or any numeric type. The model cast MUST reflect `'estado' => 'string'`.

#### Scenario: Migration normalizes legacy numeric/null values

- GIVEN the `prestamo_individual` table has rows where `estado` is `NULL` or `'0.00'`
- WHEN the migration runs
- THEN all such rows MUST have `estado` set to `'Pendiente'`
- AND no row SHALL have a numeric or NULL `estado` after migration

#### Scenario: Writing a string state persists correctly

- GIVEN a `PrestamoIndividual` instance exists with `estado = 'Pendiente'`
- WHEN `$pi->estado = 'Aprobado'` is saved
- THEN `PrestamoIndividual::find($pi->id)->estado` MUST equal `'Aprobado'`

#### Scenario: Writing a numeric-like string is rejected or normalized

- GIVEN a new `PrestamoIndividual` is created
- WHEN `estado` is set to `'0.00'`
- THEN the persisted value MUST NOT be interpreted as a float or cast to numeric

#### Scenario: Migration is reversible (down)

- GIVEN the migration has been run
- WHEN `down()` is called
- THEN the column MUST revert to its prior type without data loss of valid string states

---

## Axis 2 — GAP-05: Seeders rebuild

### Requirement: Obsolete seeders MUST be deleted

The following 9 seeder classes MUST NOT exist in the codebase after the change:
`FixPruebaAsesorSeeder`, `DeepDiagnosticAsesorSeeder`, `CheckAllAsesorUsersSeeder`,
`FixAsesorUserAssociationSeeder`, `TestNotificationSeeder`, `AuditFase6PermisosSeeder`,
`AssignShieldPermissionsToAsesorSeeder`, `TestAsesorPermissionsSeeder`,
`TestAsesorCreateClienteSeeder`.

#### Scenario: Deleted seeders are not referenced anywhere

- GIVEN the 9 seeders are deleted
- WHEN a project-wide search for any of their class names is performed
- THEN zero references MUST be found in any PHP file

### Requirement: DatabaseSeeder MUST be a clean orchestrator

`DatabaseSeeder` MUST call only the seeders needed for the canonical workflow. It MUST NOT reference deleted seeder classes. It MUST NOT contain inline data creation logic.

#### Scenario: db:seed runs without error

- GIVEN a fresh migrated database
- WHEN `php artisan db:seed` is executed
- THEN it MUST exit with code 0
- AND the log MUST NOT contain any class-not-found or missing-method error

### Requirement: PrestamosDesarrolloSeeder MUST create a canonical workflow fixture

`PrestamosDesarrolloSeeder` MUST create at minimum: one `Grupo`, one `Prestamo`, one `Cliente`, one `PrestamoIndividual`, and one `cuota_individual` record in a consistent state.

#### Scenario: Seeder creates canonical cuota_individual

- GIVEN a fresh migrated database
- WHEN `PrestamosDesarrolloSeeder` runs
- THEN a `cuota_individual` row MUST exist linked to the seeded `PrestamoIndividual`
- AND `prestamo_individual.estado` MUST be a valid string state (not NULL, not `'0.00'`)

### Requirement: TestWorkflowCompletoPrestamosSeeder MUST use current state constants

The seeder MUST NOT reference `ESTADO_POR_DESEMBOLSAR` or `ESTADO_DESEMBOLSADO`. It MUST use the constants defined in `Prestamo::ESTADOS` (or equivalent) that exist after Axis 3A cleanup.

#### Scenario: Seeder runs after GAP-03 migration

- GIVEN the database has the string `estado` column from Axis 1
- WHEN `TestWorkflowCompletoPrestamosSeeder` runs
- THEN no SQL error or invalid enum/type error MUST occur

---

## Axis 3 — GAP-01: Unlock canonical payment path

### Requirement: pagos.cuota_grupal_id MUST be nullable

The column `pagos.cuota_grupal_id` MUST allow NULL at the database level. The `Pago` model MUST NOT declare this FK as `required` in `$fillable` logic. Its cast/relation MUST be nullable-safe.

#### Scenario: Pago created without cuota_grupal_id

- GIVEN a valid `aplicacion_pago` record exists
- WHEN a `Pago` is created with `cuota_grupal_id = null`
- THEN the record MUST persist successfully
- AND `Pago::find($id)->cuota_grupal_id` MUST be `null`

#### Scenario: Pago created with cuota_grupal_id (backwards compatibility)

- GIVEN a `CuotasGrupales` record exists
- WHEN a `Pago` is created with a valid `cuota_grupal_id`
- THEN the record MUST persist successfully
- AND the `cuotaGrupal()` relation MUST resolve to the correct `CuotasGrupales` instance

### Requirement: moras.cuota_grupal_id MUST be nullable

The column `moras.cuota_grupal_id` MUST allow NULL at the database level. The `Mora` model MUST handle a null `cuota_grupal_id` without throwing exceptions in any existing helper methods.

#### Scenario: Mora created without cuota_grupal_id

- GIVEN no `CuotasGrupales` is being referenced
- WHEN a `Mora` is created with `cuota_grupal_id = null`
- THEN the record MUST persist
- AND `$mora->getDiasAtrasoAttribute()` MUST return `0` (not throw)

### Requirement: Service layer MUST route new payments through canonical path

When a payment is processed for a `cuota_individual`-based loan, the service MUST create records via `aplicacion_pago`, NOT via `cuotas_grupales`. Writing `cuota_grupal_id` on a canonical-path payment MUST NOT occur.

#### Scenario: Canonical payment does not touch cuotas_grupales

- GIVEN a `PrestamoIndividual` has a `cuota_individual` record
- WHEN a payment is processed through the service layer
- THEN no new `pagos` row with a non-null `cuota_grupal_id` SHALL be created for that payment
- AND an `aplicacion_pago` record MUST exist linking the payment to the `cuota_individual`

---

## Axis 4 — GAP-02: SeparacionService

### Requirement: SeparacionService::separarClienteMoroso MUST be atomic

The method signature MUST be:
```
SeparacionService::separarClienteMoroso(
    Prestamo $origen,
    Cliente $cliente,
    User $ejecutor,
    string $motivo
): Prestamo
```
The entire operation MUST execute within a single database transaction. If any step fails, ALL changes MUST be rolled back.

#### Scenario: Happy path — full chain persists

- GIVEN `$origen` is a `Prestamo` with at least one `cuota_individual` for `$cliente`
- WHEN `separarClienteMoroso($origen, $cliente, $ejecutor, $motivo)` is called
- THEN a new `Prestamo` (individual) MUST be created with `estado = 'Activo'` and `monto` equal to the calculated debt
- AND a new `cuota_individual` MUST exist linked to the new `Prestamo`
- AND `separaciones_clientes.prestamo_nuevo_id` MUST be updated to the new `Prestamo->id`
- AND an `audit_logs` entry MUST exist with `subject_type = Prestamo::class` referencing the new prestamo
- AND the return value MUST be the newly created `Prestamo` instance

#### Scenario: Missing cuota_individual throws DomainException

- GIVEN `$origen` is a `Prestamo` that has NO `cuota_individual` for `$cliente`
- WHEN `separarClienteMoroso($origen, $cliente, $ejecutor, $motivo)` is called
- THEN a `\DomainException` MUST be thrown
- AND no database rows SHALL be created or modified

#### Scenario: Transaction rollback on mid-chain failure

- GIVEN `$origen` is valid and `cuota_individual` exists
- WHEN an exception occurs after the new `Prestamo` is saved but before `audit_logs` is written
- THEN the new `Prestamo`, `cuota_individual`, and `separaciones_clientes` update MUST all be rolled back
- AND the database MUST be in the same state as before the call

### Requirement: separaciones_clientes.prestamo_nuevo_id MUST be a nullable FK

The column MUST be `nullable`, referencing `prestamos.id`. It MUST NOT block inserts to `separaciones_clientes` when the new prestamo is not yet known.

#### Scenario: Migration adds nullable FK without breaking existing rows

- GIVEN existing `separaciones_clientes` rows with no `prestamo_nuevo_id` value
- WHEN the migration runs
- THEN all existing rows MUST have `prestamo_nuevo_id = null`
- AND no existing row MUST be deleted or error

---

## Axis 5 — GAP-04: Reagrupaciones JSON to pivot

### Requirement: reagrupacion_cliente pivot table MUST exist

A table `reagrupacion_cliente` MUST be created with columns: `reagrupacion_id` (FK → `reagrupaciones.id`), `cliente_id` (FK → `clientes.id`), `tipo` (ENUM `['trasladado', 'retenido']`). No surrogate PK is required; composite PK on `(reagrupacion_id, cliente_id)` is acceptable.

#### Scenario: Pivot allows a client to be linked as trasladado

- GIVEN a `Reagrupacion` record exists
- WHEN a row is inserted into `reagrupacion_cliente` with `tipo = 'trasladado'`
- THEN `Reagrupacion::find($id)->clientesTrasladados()` MUST return a `Collection` containing that `Cliente`

#### Scenario: Pivot allows a client to be linked as retenido

- GIVEN a `Reagrupacion` record exists
- WHEN a row is inserted into `reagrupacion_cliente` with `tipo = 'retenido'`
- THEN `Reagrupacion::find($id)->clientesRetenidos()` MUST return a `Collection` containing that `Cliente`

### Requirement: Existing JSON data MUST be migrated to pivot rows

All values in `reagrupaciones.clientes_trasladados` (JSON array of IDs) and `reagrupaciones.clientes_retenidos` (JSON array of IDs) MUST be converted to rows in `reagrupacion_cliente` before the JSON columns are dropped.

#### Scenario: Data migration preserves all client associations

- GIVEN a `Reagrupacion` row has `clientes_trasladados = [1, 2]` and `clientes_retenidos = [3]`
- WHEN the data migration runs
- THEN `reagrupacion_cliente` MUST have 3 rows for that `reagrupacion_id`
- AND rows for clients 1 and 2 MUST have `tipo = 'trasladado'`
- AND row for client 3 MUST have `tipo = 'retenido'`

#### Scenario: Drop migration runs after data migration

- GIVEN the data migration has been applied
- WHEN the drop-JSON-columns migration runs
- THEN `reagrupaciones.clientes_trasladados` and `reagrupaciones.clientes_retenidos` MUST NOT exist
- AND no `Reagrupacion` model cast for these columns MUST remain

### Requirement: Reagrupacion model MUST expose typed pivot relations

The `Reagrupacion` model MUST expose:
- `clientesTrasladados(): BelongsToMany` — returns clients where `pivot.tipo = 'trasladado'`
- `clientesRetenidos(): BelongsToMany` — returns clients where `pivot.tipo = 'retenido'`

Both MUST use `withPivot('tipo')`. The model MUST NOT have `$casts` entries for `clientes_trasladados` or `clientes_retenidos` arrays after this change.

#### Scenario: clientesTrasladados returns only trasladado clients

- GIVEN a `Reagrupacion` has 2 trasladado and 1 retenido client in the pivot
- WHEN `$reagrupacion->clientesTrasladados` is accessed
- THEN the collection MUST have exactly 2 items
- AND each item MUST be a `Cliente` instance

#### Scenario: casts array no longer contains JSON columns

- GIVEN the drop migration has been applied
- WHEN `(new Reagrupacion)->getCasts()` is called
- THEN the result MUST NOT contain keys `'clientes_trasladados'` or `'clientes_retenidos'`

---

## Cross-Cutting Constraints

- All migrations MUST be incremental — `migrate:reset` MUST NOT be required.
- Every service method that modifies more than one table MUST wrap the operation in `DB::transaction()`.
- Every state-changing operation MUST produce an `audit_logs` entry (polymorphic).
- All new PHP methods MUST declare strict return types.
- Zero references to `ESTADO_POR_DESEMBOLSAR` or `ESTADO_DESEMBOLSADO` MUST remain after Axis 2.
- `php artisan test` MUST pass 100% after each axis is applied independently.
