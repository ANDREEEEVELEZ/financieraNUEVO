# Proposal: dominio-pagos-mora-retanqueo

**Status:** Implementado (Ejes 1-6 completos; 6.2 confirmado una vez, pendiente re-confirmación tras fix de entorno local; 6.3 pendiente de ejecución manual) | **Date:** 2026-07-21 | **Artifact Store:** hybrid (engram + openspec) | **TDD:** deshabilitado para este change (decisión explícita del usuario, override del Strict TDD Mode global del repo)

## 1. Intent

### Problem

La consolidación ledger-derived hecha en PR2–PR5 (SDD "core-contable-seguridad") dejó el nivel **grupal** del dominio de pagos sólido (`SaldoCuotaService` como fuente única de saldo/mora para `CuotasGrupales`), pero dejó sin resolver:

1. Dos fórmulas de mora (`Mora::calcularMontoMora()` a nivel grupal, `CuotaIndividual::moraCalculada()` a nivel individual) implementadas de forma hardcodeada y desconectada, sin un patrón compartido ni configuración por producto financiero — a pesar de que el usuario ya tenía en mente un patrón Strategy para esto.
2. La mora pagada a nivel individual (`AplicacionPago.monto_aplicado_mora`) se escribe pero nunca se lee de vuelta — `moraCalculada()` puede re-cobrar mora ya pagada en pagos parciales.
3. El check de quórum de retanqueo grupal (`ElegibilidadRetanqueoGrupal`) tiene un bug (numerador = denominador, siempre da 1.0) y además **no está conectado** al flujo real de aprobación (`RetanqueoWorkflowService::aprobarRetanqueo()`), que hoy solo valida "1 cuota pendiente".
4. No existe un equivalente a `SaldoCuotaService` para `CuotaIndividual` — el saldo es columnas mutables (`saldo_capital`/`saldo_interes`) más una fórmula de mora separada, sin un único punto de verdad.
5. El waterfall de distribución de pagos (interés→capital) está duplicado entre `DistribuyeEnCuotasIndividuales` (trait, usado por cobranza y retanqueo) y `PagoService::registrarCanonico()` (reimplementación inline).
6. `MorosoSeparationService` y el flujo de retanqueo no coordinan entre sí: un cliente puede ser separado del grupo mientras participa en un retanqueo en curso, sin ningún check cruzado.

### Verificado: no se solapa con `modelo-financiero-v2`

El change `modelo-financiero-v2` (archivado, commit `a4e637e`, 2026-05-29) excluye explícitamente la reescritura de mora y el refactor de `RetanqueoService` de su alcance. Este change es nuevo y separado.

### Decisiones de negocio

- **Mora**: formalizar como patrón Strategy (mismo espíritu que `ElegibilidadRetanqueoStrategy`), configurable vía `producto_financiero`. La fórmula grupal (S/ 1 × integrantes × días) es una tasa fija **intencional**, no un placeholder — no se "unifica" a una sola fórmula, se hacen ambas seleccionables.
- **Coordinación retanqueo ↔ separación**: separación bloquea retanqueo — un cliente separado no puede quedar participando en un retanqueo grupal.

## 2. Scope

Ver `design.md` para el diseño técnico completo, organizado en 6 Ejes con dependencias explícitas. Ver `tasks.md` para el checklist de implementación.

### Out of scope

- Tocar el contrato o comportamiento de `SaldoCuotaService` (nivel grupal) — debe seguir pasando sus tests sin cambios.
- Rediseñar el modelo de estados (FSM) de `Prestamo`.
- Cualquier cambio de UI/Filament más allá de lo estrictamente necesario para que los flujos sigan funcionando.

## 3. Modo de ejecución

SDD sin TDD estricto: implementación directa por Eje, con tests actualizados/extendidos junto a cada Eje (no como par RED→GREEN obligatorio previo). Ejes secuenciados por dependencia (ver design.md); Eje 1 y Eje 5 pueden ejecutarse en paralelo con 2–4 si se prefiere.
