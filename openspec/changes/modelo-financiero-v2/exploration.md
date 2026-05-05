# Exploration: modelo-financiero-v2

**Date**: 2026-04-29
**Status**: done

## Context

The system has TWO coexisting payment/installment systems with no bridge.

**Legacy System (to phase out):**
`prestamos` → `cuotas_grupales` → `pagos` → `detalles_pago` → `prestamo_individual`
- `pagos.cuota_grupal_id` FK NOT NULL
- `moras.cuota_grupal_id` FK NOT NULL (ADDITIONAL FINDING)

**Canonical System (to preserve and complete):**
`prestamos` → `cuota_individual` (per-client, updatable balances)
`pagos` → `aplicacion_pago` → `cuota_individual` (capital/interest/mora breakdown)
`ajuste_deuda` → `cuota_individual`

## Gaps Identified

### GAP-01 — Dual system without bridge (CRITICAL)
`pagos.cuota_grupal_id` and `moras.cuota_grupal_id` are NOT NULL. Must be made nullable to unlock canonical payment path. Nullable column alone is insufficient — service layer must route new payments through `aplicacion_pago`.

### GAP-02 — separaciones_clientes missing FK to new loan (CRITICAL)
Records debt snapshot but has no `prestamo_nuevo_id` FK to the individual loan created for the delinquent client. Separation is recorded but not operationally linked to the debt recovery circuit.

### GAP-03 — prestamo_individual.estado is decimal (BUG — silent corruption)
Column declared as `decimal` instead of `string/enum`. Every call to `update(['estado' => 'Aprobado'])` silently writes `0.00`. Any existing DB is already corrupted on this field.

### GAP-04 — reagrupaciones.clientes_trasladados as JSON (NORMALIZATION)
Client IDs in JSON breaks referential integrity. No FK, no JOIN-ability. Needs pivot table `reagrupacion_cliente`.

### GAP-05 — Broken seeders (CI FATAL ERROR)
- `DatabaseSeeder` empty — seeds nothing useful
- `TestWorkflowCompletoPrestamosSeeder` references `ESTADO_POR_DESEMBOLSAR` (eliminated in simplify_prestamo_estados migration) — fatal error on run
- `RetanqueoTestSeeder` queries `cuotasGrupales` — no data, no-op
- 8+ diagnostic seeders with no dev value

## Recommended Approach: Incremental by axis

Order of execution (dependency-safe):

1. **GAP-03** — Fix decimal→string on `prestamo_individual.estado` (stop corruption immediately)
2. **GAP-01** — Make `pagos.cuota_grupal_id` and `moras.cuota_grupal_id` nullable + service routing
3. **GAP-02** — Add `prestamo_nuevo_id` to `separaciones_clientes` + create `SeparacionService`
4. **GAP-05** — Rebuild seeders (enables proper TDD fixtures)
5. **GAP-04** — Normalize `reagrupaciones` JSON → pivot table (data migration last)

## Risks

- **GAP-03**: Ongoing silent corruption — every state transition on `prestamo_individual` writes `0.00` to `estado`. Fix first.
- **GAP-05**: `TestWorkflowCompletoPrestamosSeeder` will fatal-error on undefined constant. Breaks CI if seeding before tests.
- **GAP-01 service gap**: nullable column alone doesn't route payments — service changes required simultaneously.
- **GAP-04 data migration**: pivot migration must extract JSON IDs before dropping columns. Rollback needs careful `down()`.

## Key Files

- `database/migrations/2025_05_12_042539_create_prestamo_individual_table.php` — decimal bug (reference)
- `database/migrations/2025_05_12_155033_create_pagos_table.php` — cuota_grupal_id NOT NULL
- `database/migrations/2025_05_12_160030_create_moras_table.php` — cuota_grupal_id NOT NULL (additional gap)
- `database/migrations/2026_01_21_000002_create_cuota_individual_table.php` — canonical system
- `database/migrations/2026_01_21_000003_create_aplicacion_pago_table.php` — canonical system
- `database/migrations/2026_03_10_000003_create_separaciones_clientes_table.php` — missing FK
- `database/migrations/2026_03_10_000004_create_reagrupaciones_table.php` — JSON clients
- `app/Models/Pago.php` — requires cuota_grupal_id
- `app/Models/PrestamoIndividual.php` — decimal estado
- `app/Services/RetanqueoService.php` — reference for SeparacionService patterns
- `database/seeders/DatabaseSeeder.php` — empty orchestrator
- `database/seeders/TestWorkflowCompletoPrestamosSeeder.php` — obsolete constants
