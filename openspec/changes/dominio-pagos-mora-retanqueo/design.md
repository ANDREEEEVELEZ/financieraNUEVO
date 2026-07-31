# Design: dominio-pagos-mora-retanqueo

**Status:** Designed | **Date:** 2026-07-21 | **Artifact Store:** hybrid

> Decisiones arquitecturales y contratos de implementación para los 6 ejes. No incluye breakdown detallado de tasks (ver tasks.md).

## Secuenciación (dependencias)

```
Eje 1 (quórum retanqueo)  ─── independiente, puede ir primero/en paralelo
Eje 2 (Mora Strategy)     ─── base para Eje 4
Eje 3 (waterfall único)   ─── consolidar antes de construir el servicio de saldo individual
Eje 4 (saldo + mora individual, ledger-derived) ─── depende de Eje 2 y Eje 3
Eje 5 (coordinación retanqueo↔separación) ─── depende de Eje 1
Eje 6 (cleanup + verificación integral) ─── depende de todos los anteriores
```

---

## Eje 1 — Fix + conectar el quórum de retanqueo grupal

**Problema real:** `ElegibilidadRetanqueoGrupal::esElegible()` (app/Domain/Prestamos/Strategies/ElegibilidadRetanqueoGrupal.php:38-53) corre la misma query para numerador y denominador → ratio siempre 1.0. Además, esta estrategia solo se usa en `RetanqueoQueryService::obtenerGruposElegibles()` (discovery/listado) — **no** en `RetanqueoWorkflowService::aprobarRetanqueo()`, que hoy solo valida "1 cuota pendiente" inline. El gate real de aprobación no verifica quórum.

**Diseño:**
1. `obtenerGruposElegibles()` (discovery, sin solicitud creada aún) mantiene solo el check estructural (1 cuota pendiente con saldo) — no hay datos de participación todavía, no tiene sentido evaluar quórum ahí.
2. El quórum real se evalúa en `aprobarRetanqueo()`, donde ya existe la `Retanqueo` con sus `RetanqueoIndividual`:
   - Denominador = `RetanqueoIndividual` del retanqueo actual con `participacion_tipo IN ['retanquea', 'no_retanquea']` (miembros originales; excluye `'nueva'`).
   - Numerador = `participacion_tipo = 'retanquea'`.
   - Comparar contra `producto_financiero.porcentaje_minimo_retanqueo`.
3. Si no se cumple, `aprobarRetanqueo()` lanza excepción bloqueante (mismo patrón que el check de "1 cuota pendiente").

**Archivos:** `app/Domain/Prestamos/Strategies/ElegibilidadRetanqueoGrupal.php`, `app/Domain/Prestamos/RetanqueoWorkflowService.php`.

---

## Eje 2 — Mora Strategy Pattern configurable por producto financiero

**Objetivo:** formalizar el patrón Strategy ya diseñado conceptualmente por el usuario (mismo espíritu que `ElegibilidadRetanqueoStrategy`), no elegir una fórmula "ganadora".

**Diseño:**
1. Interface `MoraCalculationStrategy` (namespace `App\Domain\Mora\Strategies`).
2. `MoraFlatPorIntegranteStrategy` — encapsula la fórmula actual de `Mora::calcularMontoMora()` (S/ 1 × integrantes × días).
3. `MoraPorcentualSobreSaldoStrategy` — encapsula la fórmula actual de `CuotaIndividual::moraCalculada()` (saldo × tasa_mora/30 × días).
4. Migración: columna `tipo_calculo_mora` en `producto_financiero`, default que preserva el comportamiento actual por tipo de producto (grupal → flat, individual → porcentual).
5. `Mora::calcularMontoMora()` y `CuotaIndividual::moraCalculada()` delegan a la estrategia resuelta desde `producto_financiero.tipo_calculo_mora` (con fallback legacy si el campo no está seteado).

**Archivos:** nuevos en `app/Domain/Mora/Strategies/`, migración en `producto_financiero`, `app/Models/ProductoFinanciero.php`, `app/Models/Mora.php`, `app/Models/CuotaIndividual.php`, bindings en `app/Providers/AppServiceProvider.php`.

---

## Eje 3 — Consolidar el waterfall de distribución

**Problema:** `DistribuyeEnCuotasIndividuales` (trait) y `PagoService::registrarCanonico()` reimplementan el mismo flujo interés→capital de forma independiente.

**Diseño:** extraer la lógica compartida a un único punto (ampliar el trait existente o extraer un servicio si `registrarCanonico()` necesita una firma distinta al operar sin `CuotasGrupales`). `registrarCanonico()` consume esa lógica en vez de reimplementarla.

**Archivos:** `app/Domain/Pagos/Concerns/DistribuyeEnCuotasIndividuales.php`, `app/Domain/Pagos/PagoService.php`.

---

## Eje 4 — Persistencia de mora individual + servicio de saldo ledger-derived

**Problema:** `AplicacionPago.monto_aplicado_mora` se escribe por integrante pero nunca se lee de vuelta — `moraCalculada()` recalcula desde cero y puede re-cobrar mora ya pagada. No existe equivalente a `SaldoCuotaService` para `CuotaIndividual`.

**Diseño (consistente con el patrón ledger-derived ya establecido a nivel grupal):**
1. `moraCalculada()` resta la mora ya pagada, derivada como `SUM(AplicacionPago.monto_aplicado_mora)` para esa `cuota_individual` — sin columna nueva, mismo enfoque que `SaldoCuotaService::moraPagada()` a nivel grupal.
2. Nuevo `SaldoCuotaIndividualService` — fuente única de verdad para saldo de `CuotaIndividual`: capital + interés (columnas, mutadas vía el waterfall del Eje 3) + mora (vía la estrategia del Eje 2, neta de lo pagado).
3. Migrar lectores existentes de `CuotaIndividual::saldoTotal()`/`moraCalculada()` al nuevo servicio.

**Archivos:** `app/Models/CuotaIndividual.php`, nuevo `app/Domain/Pagos/SaldoCuotaIndividualService.php`, `app/Contracts/SaldoCuotaServiceInterface.php`, `app/Domain/Grupos/MorosoSeparationService.php`.

---

## Eje 5 — Coordinación retanqueo ↔ separación (separación bloquea retanqueo)

**Problema:** sin check cruzado. Dado que `SeparacionCliente` se ejecuta atómicamente (no hay estado "pendiente" intermedio), el punto de enforcement es del lado de retanqueo:

**Diseño:**
- Antes de aprobar/ejecutar un retanqueo (`aprobarRetanqueo()` y/o `ejecutarRetanqueo()`), revalidar que cada `RetanqueoIndividual` con `participacion_tipo IN ['retanquea','no_retanquea']` siga correspondiendo a un cliente activo en el grupo (`fecha_salida IS NULL`). Si algún integrante fue separado después de crearse la solicitud, bloquear con error accionable.
- Guard adicional en `crearSolicitudRetanqueo()`: rechazar de entrada participantes con `SeparacionCliente.estado='ejecutada'` para ese `prestamo_id` (defensa en profundidad).

**Archivos:** `app/Domain/Prestamos/RetanqueoWorkflowService.php`, `app/Domain/Prestamos/RetanqueoEjecucionService.php`.

---

## Eje 6 — Cleanup y verificación integral

- Auditar referencias muertas a las fórmulas de mora hardcodeadas reemplazadas en Eje 2.
- Suite completa de tests en verde.
- `php artisan migrate:fresh --seed` en DB de desarrollo.
- Verificación manual: quórum insuficiente bloquea aprobación (Eje 1), pago parcial de mora dos veces no re-cobra (Eje 4), separación bloquea retanqueo en curso (Eje 5).
- Confirmar que `SaldoCuotaService` (grupal) no cambia de contrato ni comportamiento.
