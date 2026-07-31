# Tasks: dominio-pagos-mora-retanqueo

> Sin TDD estricto (decisión explícita del usuario). Tareas de implementación directa; tests se agregan/actualizan junto a cada Eje, no como par RED→GREEN previo.
> Ejes 1 y 5 pueden correr en paralelo con 2-4. Todas las rutas relativas a `C:/Users/Pier/Desktop/EC-Antigravity`.

## Eje 1 — Quórum de retanqueo grupal

- [x] 1.1 Separar en `ElegibilidadRetanqueoGrupal` el check estructural (discovery) del check de quórum real.
- [x] 1.2 Corregir el cálculo del ratio (numerador ≠ denominador): denominador = `RetanqueoIndividual` con `participacion_tipo IN ['retanquea','no_retanquea']`, numerador = `participacion_tipo = 'retanquea'`.
- [x] 1.3 Conectar el check de quórum a `RetanqueoWorkflowService::aprobarRetanqueo()` (hoy no lo valida).
- [x] 1.4 Actualizar/extender `tests/Unit/Domain/ElegibilidadRetanqueoStrategyTest.php` y `tests/Feature/Domain/Prestamos/RetanqueoElegibilidadLedgerMigrationTest.php`.

## Eje 2 — Mora Strategy Pattern

- [x] 2.1 Crear interface `MoraCalculationStrategy` (`app/Domain/Mora/Strategies/`).
- [x] 2.2 Implementar `MoraFlatPorIntegranteStrategy` (encapsula fórmula actual de `Mora::calcularMontoMora()`).
- [x] 2.3 Implementar `MoraPorcentualSobreSaldoStrategy` (encapsula fórmula actual de `CuotaIndividual::moraCalculada()`).
- [x] 2.4 Migración: columna `tipo_calculo_mora` en `producto_financiero` con default que preserva comportamiento actual por tipo.
- [x] 2.5 `Mora::calcularMontoMora()` y `CuotaIndividual::moraCalculada()` delegan a la estrategia resuelta (con fallback legacy).
- [x] 2.6 Registrar bindings en `app/Providers/AppServiceProvider.php`. (Decisión: sin binding — mismo patrón que `ElegibilidadRetanqueoGrupal`/`ElegibilidadRetanqueoIndividual`, instanciación directa vía `new` desde `ProductoFinanciero::moraStrategy()`, elegida por dato no por contenedor.)
- [x] 2.7 Actualizar `tests/Unit/MoraTest.php` y agregar tests unitarios para ambas estrategias.

## Eje 3 — Waterfall único

- [x] 3.1 Extraer/consolidar la lógica interés→capital compartida entre `DistribuyeEnCuotasIndividuales` y `PagoService::registrarCanonico()`.
- [x] 3.2 `registrarCanonico()` consume la lógica consolidada en vez de reimplementarla.
- [x] 3.3 Verificar `tests/Feature/Domain/RetanqueoSplitRegressionTest.php` sigue en verde (usa el trait).

## Eje 4 — Persistencia de mora individual + saldo ledger-derived

- [x] 4.1 `moraCalculada()` resta `SUM(AplicacionPago.monto_aplicado_mora)` de la cuota (ledger-derived, sin columna nueva).
- [x] 4.2 Crear `SaldoCuotaIndividualService` (capital + interés + mora neta, vía estrategia del Eje 2).
- [x] 4.3 Evaluar extensión vs. interface hermana en `app/Contracts/SaldoCuotaServiceInterface.php`. (Decisión: interface hermana `SaldoCuotaIndividualServiceInterface` — ver justificación en el archivo.)
- [x] 4.4 Migrar `MorosoSeparationService` a usar el nuevo servicio en vez de `moraCalculada()` directo.
- [x] 4.5 Test: pago parcial de mora dos veces no debe re-cobrar los mismos días. Extender `tests/Unit/Domain/Pagos/SaldoCuotaServiceTest.php`-style para el nuevo servicio.

## Eje 5 — Coordinación retanqueo ↔ separación

- [x] 5.1 Revalidación en `aprobarRetanqueo()`/`ejecutarRetanqueo()`: cada participante `retanquea`/`no_retanquea` debe seguir activo en el grupo (`fecha_salida IS NULL`).
- [x] 5.2 Guard en `crearSolicitudRetanqueo()` contra clientes con `SeparacionCliente.estado='ejecutada'` para ese préstamo.
- [x] 5.3 Test: separar a un cliente en un retanqueo en curso debe bloquear la aprobación. Extender `tests/Feature/MorosoSeparationServiceTest.php` y tests de retanqueo.

## Eje 6 — Cleanup y verificación integral (depende de todos los anteriores)

- [x] 6.1 Auditar referencias muertas a las fórmulas de mora hardcodeadas reemplazadas en Eje 2. Se encontró y eliminó `app/Services/Mora/*` (scaffold Strategy previo, nunca conectado — 0 referencias externas); `context.md` actualizado. De paso se corrigieron 2 bugs adicionales de `diffInDays` (Carbon 3): `app/Models/Mora.php` (defensivo, sin cambio de valor) y `app/Filament/Dashboard/Pages/Moras.php` (bug real: días negativos + mutación duplicada de Carbon).
- [x] 6.2 `php artisan test` completo — confirmado en verde una vez (654 passed, 17 failed = baseline exacto de Auth API sin relación, 1 skipped) antes de que el entorno local perdiera la extensión `pdo_sqlite` (bloqueo de entorno, no de código — ver engram `sdd/dominio-pagos-mora-retanqueo/apply-progress`). Pendiente re-confirmación del usuario tras habilitar pdo_sqlite.
- [ ] 6.3 `php artisan migrate:fresh --seed` en DB de desarrollo sin errores. NO ejecutado — sandbox denegó lectura de `.env` para confirmar el DB_DATABASE objetivo de forma segura. Requiere ejecución manual del usuario.
- [x] 6.4 Verificación manual de los 3 flujos críticos (quórum, mora parcial repetida, separación bloqueando retanqueo). Cubierta por los tests dedicados de Ejes 1/4/5, todos verdes en la corrida limpia de 6.2.
- [x] 6.5 Confirmar que `SaldoCuotaService` (grupal) no cambió de contrato ni comportamiento. Confirmado: `app/Domain/Pagos/SaldoCuotaService.php` no fue tocado en ningún Eje; su test suite pasó como parte de los 654 en la corrida limpia de 6.2.
