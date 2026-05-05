# Tasks: modelo-financiero-v2

**Status:** Ready | **Date:** 2026-04-29 | **Strict TDD:** enabled

> RED task (failing test) → GREEN task (implementation) → per axis.
> Ejes can run in parallel once their Eje 0 (migrations) prerequisite is met.

---

## Eje 0 — Infrastructure: Migrations (sequential, all axes depend on these)

- [ ] 0.1 — Crear migración `normalize_prestamo_individual_estado_data` (UPDATE NULL/0.00/0 → 'Pendiente')
  - Archivo: `database/migrations/2026_04_29_100001_normalize_prestamo_individual_estado_data.php`
  - Criterio: `php artisan migrate` aplica sin error; up() actualiza rows sucios; down() es no-op documentado.

- [ ] 0.2 — Crear migración `change_prestamo_individual_estado_to_string` (decimal → VARCHAR(50) DEFAULT 'Pendiente')
  - Archivo: `database/migrations/2026_04_29_100002_change_prestamo_individual_estado_to_string.php`
  - Criterio: `SHOW COLUMNS FROM prestamo_individual` muestra varchar(50); down() revierte tipo.

- [ ] 0.3 — Crear migración `make_pagos_cuota_grupal_id_nullable`
  - Archivo: `database/migrations/2026_04_29_100003_make_pagos_cuota_grupal_id_nullable.php`
  - Criterio: `pagos.cuota_grupal_id` acepta NULL; down() revierte a NOT NULL.

- [ ] 0.4 — Crear migración `make_moras_cuota_grupal_id_nullable`
  - Archivo: `database/migrations/2026_04_29_100004_make_moras_cuota_grupal_id_nullable.php`
  - Criterio: `moras.cuota_grupal_id` acepta NULL; down() revierte.

- [ ] 0.5 — Crear migración `add_prestamo_nuevo_id_to_separaciones_clientes` (nullable FK → prestamos)
  - Archivo: `database/migrations/2026_04_29_100005_add_prestamo_nuevo_id_to_separaciones_clientes.php`
  - Criterio: columna existe, nullable, FK con nullOnDelete; rows existentes quedan null; índice `sep_prestamo_nuevo_index` creado.

- [ ] 0.6 — Crear migración `create_reagrupacion_cliente_table` (pivot con ENUM tipo)
  - Archivo: `database/migrations/2026_04_29_100006_create_reagrupacion_cliente_table.php`
  - Criterio: tabla con id, reagrupacion_id FK, cliente_id FK, tipo ENUM('trasladado','retenido'), unique(reagrupacion_id, cliente_id).

- [ ] 0.7 — Crear migración `migrate_reagrupaciones_json_to_pivot` (lee JSON → insertOrIgnore en pivot)
  - Archivo: `database/migrations/2026_04_29_100007_migrate_reagrupaciones_json_to_pivot.php`
  - Criterio: rows en reagrupacion_cliente = suma de IDs en ambos JSON por reagrupacion; down() elimina filas migradas.

- [ ] 0.8 — Crear migración `drop_json_columns_from_reagrupaciones`
  - Archivo: `database/migrations/2026_04_29_100008_drop_json_columns_from_reagrupaciones.php`
  - Criterio: columnas clientes_trasladados y clientes_retenidos no existen; down() las restaura como JSON nullable.

---

## Eje 1 — GAP-03: PrestamoIndividual.estado (depende de 0.1, 0.2)

- [ ] 1.1 RED — Escribir test: estado columna es string, write 'Aprobado' persiste 'Aprobado'
  - Archivo: `tests/Unit/PrestamoIndividualEstadoTest.php` (crear)
  - Criterio: `php artisan test --filter PrestamoIndividualEstadoTest` falla antes de cambios al modelo.

- [ ] 1.2 GREEN — Actualizar PrestamoIndividual: agregar constantes ESTADO_*, eliminar cast decimal de estado, agregar `estaFinalizado(): bool`
  - Archivo: `app/Models/PrestamoIndividual.php`
  - Criterio: test 1.1 pasa; no queda cast numérico para 'estado'; constantes definidas como `public const`.

- [ ] 1.3 RED — Escribir test: valor '0.00' no se interpreta como float (spec "Writing a numeric-like string")
  - Archivo: `tests/Unit/PrestamoIndividualEstadoTest.php`
  - Criterio: test nuevo falla antes del cast correcto.

- [ ] 1.4 GREEN — Confirmar que sin cast numérico el test 1.3 pasa; ajustar si MySQL interpola valor
  - Archivo: `app/Models/PrestamoIndividual.php`
  - Criterio: todos los tests PrestamoIndividualEstadoTest pasan; `php artisan test` verde global.

---

## Eje 2 — GAP-05: Seeders rebuild (depende de Eje 1 completado)

- [ ] 2.1 — Eliminar los 9 seeders obsoletos
  - Archivos: `database/seeders/{FixPruebaAsesorSeeder,DeepDiagnosticAsesorSeeder,CheckAllAsesorUsersSeeder,FixAsesorUserAssociationSeeder,TestNotificationSeeder,AuditFase6PermisosSeeder,AssignShieldPermissionsToAsesorSeeder,TestAsesorPermissionsSeeder,TestAsesorCreateClienteSeeder}.php`
  - Criterio: archivos borrados; `rg` de sus class names en todo el proyecto = 0 hits.

- [ ] 2.2 RED — Escribir test: SeedersIntegridadTest verifica que db:seed corre y deja cuota_individual válida
  - Archivo: `tests/Feature/SeedersIntegridadTest.php` (crear)
  - Criterio: test falla porque UsersSeeder/ClientesGruposSeeder/PrestamosDesarrolloSeeder no existen aún.

- [ ] 2.3 GREEN — Crear UsersSeeder (Admin, JC, JO, Asesor con roles)
  - Archivo: `database/seeders/UsersSeeder.php` (crear)
  - Criterio: seeder crea 4 usuarios con roles asignados sin error.

- [ ] 2.4 GREEN — Crear ClientesGruposSeeder (grupo + clientes asociados)
  - Archivo: `database/seeders/ClientesGruposSeeder.php` (crear)
  - Criterio: seeder crea grupo con al menos 1 cliente sin error.

- [ ] 2.5 GREEN — Crear PrestamosDesarrolloSeeder con helper `crearPrestamoConCuotasIndividuales()`
  - Archivo: `database/seeders/PrestamosDesarrolloSeeder.php` (crear)
  - Criterio: crea Prestamo + cuota_individual ligada; estado es string válido (no NULL, no '0.00').

- [ ] 2.6 GREEN — Reescribir DatabaseSeeder, TestWorkflowCompletoPrestamosSeeder, RetanqueoTestSeeder
  - Archivos: `database/seeders/DatabaseSeeder.php`, `database/seeders/TestWorkflowCompletoPrestamosSeeder.php`, `database/seeders/RetanqueoTestSeeder.php`
  - Criterio: `php artisan db:seed` exitoso; test 2.2 pasa; `rg 'ESTADO_POR_DESEMBOLSAR|ESTADO_DESEMBOLSADO'` en seeders = 0 hits.

---

## Eje 3 — GAP-01: Canonical payment path (depende de 0.3, 0.4)

- [ ] 3.1 RED — Escribir test: Mora sin cuota_grupal_id no lanza excepción en getDiasAtrasoAttribute()
  - Archivo: `tests/Unit/MoraTest.php` (agregar escenario)
  - Criterio: test falla porque getDiasAtrasoAttribute() no tiene null guard aún.

- [ ] 3.2 GREEN — Actualizar modelo Mora: null guard en getDiasAtrasoAttribute() → devuelve 0 si cuotaGrupal es null
  - Archivo: `app/Models/Mora.php`
  - Criterio: test 3.1 pasa.

- [ ] 3.3 RED — Escribir test: Pago con cuota_grupal_id=null persiste; Pago con cuota_grupal_id válido persiste
  - Archivo: `tests/Unit/PagoTest.php` (agregar escenarios)
  - Criterio: tests fallan porque cuota_grupal_id es NOT NULL en DB.

- [ ] 3.4 GREEN — Actualizar modelo Pago: cuotaGrupal() nullable-safe; tests 3.3 pasan tras migración 0.3
  - Archivo: `app/Models/Pago.php`
  - Criterio: ambos escenarios pasan; cuota_grupal_id puede ser null.

- [ ] 3.5 RED — Escribir test: PagoService::registrarCanonico() crea Pago + AplicacionPago, NO escribe cuota_grupal_id
  - Archivo: `tests/Feature/PagoCanonicoTest.php` (crear)
  - Criterio: test falla porque registrarCanonico() no existe.

- [ ] 3.6 GREEN — Implementar PagoService::registrarCanonico() con DB::transaction y AuditService
  - Archivo: `app/Services/PagoService.php`
  - Criterio: test 3.5 pasa; Pago.cuota_grupal_id=null; AplicacionPago ligada a cuota_individual; audit_log creado.

- [ ] 3.7 — Sweep tests existentes que asumen cuota_grupal_id NOT NULL y corregir
  - Archivos: `tests/Unit/PagoTest.php`, `tests/Feature/PrestamoWorkflowTest.php` (todos los relevantes)
  - Criterio: `php artisan test` 100% verde sin romper comportamiento existente.

---

## Eje 4 — GAP-02: SeparacionService (depende de 0.5, Eje 1, Eje 3)

- [ ] 4.1 RED — Escribir test: separarClienteMoroso happy path (Prestamo nuevo + cuota_individual + audit_log)
  - Archivo: `tests/Feature/SeparacionServiceTest.php` (crear)
  - Criterio: test falla porque firma/implementación no existe.

- [ ] 4.2 RED — Escribir test: DomainException cuando no hay cuota_individual para cliente; DomainException si ya separado
  - Archivo: `tests/Feature/SeparacionServiceTest.php`
  - Criterio: tests fallan porque método no lanza excepciones correctas.

- [ ] 4.3 RED — Escribir test: rollback completo si falla después de crear Prestamo pero antes de audit_log
  - Archivo: `tests/Feature/SeparacionServiceTest.php`
  - Criterio: test falla porque no hay transacción implementada.

- [ ] 4.4 GREEN — Reescribir SeparacionClienteService::separarClienteMoroso() con flujo de 8 pasos en DB::transaction
  - Archivo: `app/Services/SeparacionClienteService.php`
  - Criterio: tests 4.1, 4.2, 4.3 pasan; alias separar() marcado @deprecated con delegación interna.

- [ ] 4.5 — Actualizar modelo SeparacionCliente: agregar prestamo_nuevo_id a $fillable; relación prestamoNuevo()
  - Archivo: `app/Models/SeparacionCliente.php`
  - Criterio: `SeparacionCliente::where('prestamo_nuevo_id', $id)` funciona; relación lazy-load resuelve.

---

## Eje 5 — GAP-04: Reagrupaciones pivot (depende de 0.6, 0.7, 0.8)

- [ ] 5.1 RED — Escribir test: clientesTrasladados() y clientesRetenidos() devuelven colecciones correctas desde pivot
  - Archivo: `tests/Feature/ReagrupacionPivotTest.php` (crear)
  - Criterio: tests fallan porque relaciones BelongsToMany no existen.

- [ ] 5.2 RED — Escribir test: getCasts() no contiene clientes_trasladados ni clientes_retenidos
  - Archivo: `tests/Feature/ReagrupacionPivotTest.php`
  - Criterio: test falla si $casts aún tiene esas claves.

- [ ] 5.3 GREEN — Actualizar modelo Reagrupacion: agregar clientesTrasladados(), clientesRetenidos() via BelongsToMany con wherePivot; eliminar casts JSON
  - Archivo: `app/Models/Reagrupacion.php`
  - Criterio: tests 5.1, 5.2 pasan; $fillable sin clientes_trasladados/retenidos.

- [ ] 5.4 GREEN — Actualizar ReagrupacionParcialService::reagrupar(): reemplazar escritura JSON por pivot attach
  - Archivo: `app/Services/ReagrupacionParcialService.php`
  - Criterio: `php artisan test` pasa; GestionIntegrantesTest existente sigue verde.

---

## Eje 6 — Cleanup & Verification (depende de todos los ejes anteriores)

- [ ] 6.1 — Auditar referencias a ESTADO_POR_DESEMBOLSAR y ESTADO_DESEMBOLSADO en todo el proyecto
  - Criterio: `rg 'ESTADO_POR_DESEMBOLSAR|ESTADO_DESEMBOLSADO'` = 0 hits en archivos PHP.

- [ ] 6.2 — Auditar referencias a los 9 seeders eliminados
  - Criterio: `rg` por cada class name = 0 hits en cualquier archivo PHP.

- [ ] 6.3 — Correr suite completa y confirmar 100% verde
  - Criterio: `php artisan test` sin flags devuelve OK, 0 failed.

- [ ] 6.4 — Verificar down() de las 8 migraciones con migrate:rollback
  - Criterio: `php artisan migrate:rollback --step=8` sin errores en DB de test.
