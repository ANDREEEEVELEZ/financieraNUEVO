<?php

use App\Contracts\RetanqueoQueryInterface;
use App\Contracts\RetanqueoWorkflowInterface;
use App\Domain\Prestamos\Strategies\ElegibilidadRetanqueoGrupal;
use App\Domain\Prestamos\Strategies\ElegibilidadRetanqueoIndividual;
use App\Models\Asesor;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\Retanqueo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * SDD core-contable-seguridad, Slice D, Requisito 4.1/R-c1.
 *
 * Caracterización: las verificaciones de elegibilidad de retanqueo
 * (ElegibilidadRetanqueoIndividual, ElegibilidadRetanqueoGrupal,
 * RetanqueoQueryService::obtenerGruposElegibles,
 * RetanqueoWorkflowService::aprobarRetanqueo) migraron de leer la columna
 * legacy `cuotas_grupales.saldo_pendiente` (ya eliminada, Req 4.1) a derivar
 * el saldo del ledger (SaldoCuotaService). Estos tests prueban que el
 * resultado de elegibilidad se deriva correctamente del ledger (Pago
 * aprobados vs. monto_cuota_grupal), sin depender de ninguna columna.
 */
it('ElegibilidadRetanqueoIndividual es elegible cuando el ledger dice saldo pendiente (sin pagos aprobados)', function () {
    $prestamo = Prestamo::factory()->create(['estado' => 'Aprobado']);

    CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 200,
        'estado_pago' => 'pendiente',
    ]);

    $strategy = new ElegibilidadRetanqueoIndividual;

    expect($strategy->esElegible($prestamo))->toBeTrue();
});

it('ElegibilidadRetanqueoIndividual NO es elegible cuando el ledger dice pagado (pago aprobado cubre el monto)', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);

    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 200,
        'estado_pago' => 'pendiente',
    ]);
    Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado' => 200,
        'monto_mora_pagada' => 0,
        'estado_pago' => 'aprobado',
    ]);

    $strategy = new ElegibilidadRetanqueoIndividual;

    expect($strategy->esElegible($prestamo))->toBeFalse();
});

it('ElegibilidadRetanqueoGrupal (rama fallback, sin producto financiero) deriva del ledger', function () {
    $prestamo = Prestamo::factory()->create(['estado' => 'Aprobado', 'grupo_id' => null]);

    CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 150,
        'estado_pago' => 'pendiente',
    ]);

    $strategy = new ElegibilidadRetanqueoGrupal;

    expect($strategy->esElegible($prestamo))->toBeTrue();
});

it('RetanqueoQueryService::obtenerGruposElegibles refleja el ledger', function () {
    $service = app(RetanqueoQueryInterface::class);

    $asesor = Asesor::factory()->create();
    $grupo = Grupo::factory()->create(['asesor_id' => $asesor->id, 'estado_grupo' => 'Activo']);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => 'Aprobado',
        'cantidad_cuotas' => 4,
    ]);

    // 3 cuotas pagadas (excluidas por estado_pago).
    CuotasGrupales::factory()->count(3)->create([
        'prestamo_id' => $prestamo->id,
        'estado_pago' => 'pagado',
    ]);

    // 1 cuota pendiente sin Pago aprobado -> ledger dice pendiente.
    CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 100,
        'estado_pago' => 'pendiente',
    ]);

    $result = $service->obtenerGruposElegibles();

    expect($result->count())->toBe(1);
    expect($result->first()->id)->toBe($grupo->id);
});

it('RetanqueoWorkflowService::aprobarRetanqueo bloquea cuando el ledger dice 2+ cuotas pendientes', function () {
    $grupo = Grupo::factory()->create();
    $prestamoAntiguo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => 'Aprobado',
    ]);

    // 2 cuotas pendientes reales (sin Pago aprobado alguno).
    CuotasGrupales::factory()->count(2)->create([
        'prestamo_id' => $prestamoAntiguo->id,
        'monto_cuota_grupal' => 100,
        'estado_pago' => 'pendiente',
    ]);

    $retanqueo = Retanqueo::create([
        'prestamo_id' => $prestamoAntiguo->id,
        'prestamo_nuevo_id' => null,
        'monto_retanqueo' => 400,
        'monto_usado_para_cubrir_antiguo' => 0,
        'monto_desembolsar' => 400,
        'monto_cuota' => null,
        'cantidad_cuotas_nuevo' => 4,
        'saldo_restante_prestamo_antiguo' => 0,
        'prestamo_antiguo_estado' => 0,
        'estado_retanqueo' => 'solicitud_pendiente',
    ]);

    $service = app(RetanqueoWorkflowInterface::class);

    expect(fn () => $service->aprobarRetanqueo($retanqueo->id))
        ->toThrow(\Exception::class, 'APROBACIÓN BLOQUEADA');
});

/**
 * Eje 1 (dominio-pagos-mora-retanqueo) — Task 1.1: la elegibilidad de
 * discovery (esElegible) ya no calcula el ratio numerador/denominador roto
 * (que siempre daba 1.0 porque ambas queries eran idénticas). Ahora es un
 * chequeo puramente estructural — igual a ElegibilidadRetanqueoIndividual —
 * independiente de si el grupo/producto financiero está configurado.
 */
it('ElegibilidadRetanqueoGrupal::esElegible es un chequeo estructural puro (1 cuota pendiente), no calcula quórum', function () {
    $grupo = Grupo::factory()->create();
    $productoConQuorumInalcanzable = \App\Models\ProductoFinanciero::factory()->create([
        'tipo' => 'grupal',
        'porcentaje_minimo_retanqueo' => 0.99,
    ]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'producto_id' => $productoConQuorumInalcanzable->id,
        'estado' => 'Aprobado',
    ]);

    CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 150,
        'estado_pago' => 'pendiente',
    ]);

    $strategy = new ElegibilidadRetanqueoGrupal;

    // Elegible pese al 99% de quórum mínimo configurado — esElegible() no
    // evalúa quórum, solo el requisito estructural de discovery.
    expect($strategy->esElegible($prestamo))->toBeTrue();
});

it('ElegibilidadRetanqueoGrupal::cumpleQuorum bloquea cuando solo 1 de 3 integrantes originales retanquea', function () {
    $grupo = Grupo::factory()->create();
    $prestamoAntiguo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => 'Aprobado',
    ]);

    $retanqueo = Retanqueo::create([
        'prestamo_id' => $prestamoAntiguo->id,
        'prestamo_nuevo_id' => null,
        'monto_retanqueo' => 500,
        'monto_usado_para_cubrir_antiguo' => 0,
        'monto_desembolsar' => 500,
        'monto_cuota' => null,
        'cantidad_cuotas_nuevo' => 4,
        'saldo_restante_prestamo_antiguo' => 0,
        'prestamo_antiguo_estado' => 0,
        'estado_retanqueo' => 'solicitud_pendiente',
    ]);

    $clientes = \App\Models\Cliente::factory()->count(3)->create();

    \App\Models\RetanqueoIndividual::create([
        'retanqueo_id' => $retanqueo->id,
        'cliente_id' => $clientes[0]->id,
        'participacion_tipo' => 'retanquea',
        'aporte_cobertura' => 0,
        'monto_solicitado' => 500,
        'monto_desembolsar' => 500,
        'aceptacion_cliente' => 0,
        'estado_retanqueo_individual' => 'propuesto',
    ]);
    \App\Models\RetanqueoIndividual::create([
        'retanqueo_id' => $retanqueo->id,
        'cliente_id' => $clientes[1]->id,
        'participacion_tipo' => 'no_retanquea',
        'aporte_cobertura' => 0,
        'monto_solicitado' => 0,
        'monto_desembolsar' => 0,
        'aceptacion_cliente' => 0,
        'estado_retanqueo_individual' => 'propuesto',
    ]);
    \App\Models\RetanqueoIndividual::create([
        'retanqueo_id' => $retanqueo->id,
        'cliente_id' => $clientes[2]->id,
        'participacion_tipo' => 'no_retanquea',
        'aporte_cobertura' => 0,
        'monto_solicitado' => 0,
        'monto_desembolsar' => 0,
        'aceptacion_cliente' => 0,
        'estado_retanqueo_individual' => 'propuesto',
    ]);

    $strategy = new ElegibilidadRetanqueoGrupal;

    // 1 de 3 = 33% < 51% (default) -> no cumple quórum.
    expect($strategy->cumpleQuorum($retanqueo->fresh()))->toBeFalse();
});

it('ElegibilidadRetanqueoGrupal::cumpleQuorum aprueba cuando el quórum sí se alcanza', function () {
    $grupo = Grupo::factory()->create();
    $prestamoAntiguo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => 'Aprobado',
    ]);

    $retanqueo = Retanqueo::create([
        'prestamo_id' => $prestamoAntiguo->id,
        'prestamo_nuevo_id' => null,
        'monto_retanqueo' => 1000,
        'monto_usado_para_cubrir_antiguo' => 0,
        'monto_desembolsar' => 1000,
        'monto_cuota' => null,
        'cantidad_cuotas_nuevo' => 4,
        'saldo_restante_prestamo_antiguo' => 0,
        'prestamo_antiguo_estado' => 0,
        'estado_retanqueo' => 'solicitud_pendiente',
    ]);

    $clientes = \App\Models\Cliente::factory()->count(3)->create();

    // 2 de 3 = 66.6% >= 51% (default) -> cumple quórum.
    \App\Models\RetanqueoIndividual::create([
        'retanqueo_id' => $retanqueo->id,
        'cliente_id' => $clientes[0]->id,
        'participacion_tipo' => 'retanquea',
        'aporte_cobertura' => 0,
        'monto_solicitado' => 500,
        'monto_desembolsar' => 500,
        'aceptacion_cliente' => 0,
        'estado_retanqueo_individual' => 'propuesto',
    ]);
    \App\Models\RetanqueoIndividual::create([
        'retanqueo_id' => $retanqueo->id,
        'cliente_id' => $clientes[1]->id,
        'participacion_tipo' => 'retanquea',
        'aporte_cobertura' => 0,
        'monto_solicitado' => 500,
        'monto_desembolsar' => 500,
        'aceptacion_cliente' => 0,
        'estado_retanqueo_individual' => 'propuesto',
    ]);
    \App\Models\RetanqueoIndividual::create([
        'retanqueo_id' => $retanqueo->id,
        'cliente_id' => $clientes[2]->id,
        'participacion_tipo' => 'no_retanquea',
        'aporte_cobertura' => 0,
        'monto_solicitado' => 0,
        'monto_desembolsar' => 0,
        'aceptacion_cliente' => 0,
        'estado_retanqueo_individual' => 'propuesto',
    ]);

    $strategy = new ElegibilidadRetanqueoGrupal;

    expect($strategy->cumpleQuorum($retanqueo->fresh()))->toBeTrue();
});

it('RetanqueoWorkflowService::aprobarRetanqueo bloquea cuando el quórum de integrantes originales no se alcanza', function () {
    $grupo = Grupo::factory()->create();
    $prestamoAntiguo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => 'Aprobado',
    ]);

    // Exactamente 1 cuota pendiente con saldo -> pasa el check estructural.
    CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamoAntiguo->id,
        'monto_cuota_grupal' => 150,
        'estado_pago' => 'pendiente',
    ]);

    $retanqueo = Retanqueo::create([
        'prestamo_id' => $prestamoAntiguo->id,
        'prestamo_nuevo_id' => null,
        'monto_retanqueo' => 500,
        'monto_usado_para_cubrir_antiguo' => 0,
        'monto_desembolsar' => 500,
        'monto_cuota' => null,
        'cantidad_cuotas_nuevo' => 4,
        'saldo_restante_prestamo_antiguo' => 0,
        'prestamo_antiguo_estado' => 0,
        'estado_retanqueo' => 'solicitud_pendiente',
    ]);

    $clientes = \App\Models\Cliente::factory()->count(3)->create();

    // Solo 1 de 3 integrantes originales retanquea -> 33% < 51%.
    \App\Models\RetanqueoIndividual::create([
        'retanqueo_id' => $retanqueo->id,
        'cliente_id' => $clientes[0]->id,
        'participacion_tipo' => 'retanquea',
        'aporte_cobertura' => 0,
        'monto_solicitado' => 500,
        'monto_desembolsar' => 500,
        'aceptacion_cliente' => 0,
        'estado_retanqueo_individual' => 'propuesto',
    ]);
    \App\Models\RetanqueoIndividual::create([
        'retanqueo_id' => $retanqueo->id,
        'cliente_id' => $clientes[1]->id,
        'participacion_tipo' => 'no_retanquea',
        'aporte_cobertura' => 0,
        'monto_solicitado' => 0,
        'monto_desembolsar' => 0,
        'aceptacion_cliente' => 0,
        'estado_retanqueo_individual' => 'propuesto',
    ]);
    \App\Models\RetanqueoIndividual::create([
        'retanqueo_id' => $retanqueo->id,
        'cliente_id' => $clientes[2]->id,
        'participacion_tipo' => 'no_retanquea',
        'aporte_cobertura' => 0,
        'monto_solicitado' => 0,
        'monto_desembolsar' => 0,
        'aceptacion_cliente' => 0,
        'estado_retanqueo_individual' => 'propuesto',
    ]);

    $service = app(RetanqueoWorkflowInterface::class);

    expect(fn () => $service->aprobarRetanqueo($retanqueo->id))
        ->toThrow(\Exception::class, 'APROBACIÓN BLOQUEADA');

    expect($retanqueo->fresh()->estado_retanqueo)->toBe('solicitud_pendiente');
});

it('RetanqueoWorkflowService::aprobarRetanqueo aprueba cuando el quórum de integrantes originales sí se alcanza', function () {
    $grupo = Grupo::factory()->create();
    $prestamoAntiguo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => 'Aprobado',
    ]);

    // Exactamente 1 cuota pendiente con saldo -> pasa el check estructural.
    CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamoAntiguo->id,
        'monto_cuota_grupal' => 150,
        'estado_pago' => 'pendiente',
    ]);

    $retanqueo = Retanqueo::create([
        'prestamo_id' => $prestamoAntiguo->id,
        'prestamo_nuevo_id' => null,
        'monto_retanqueo' => 1000,
        'monto_usado_para_cubrir_antiguo' => 0,
        'monto_desembolsar' => 1000,
        'monto_cuota' => null,
        'cantidad_cuotas_nuevo' => 4,
        'saldo_restante_prestamo_antiguo' => 0,
        'prestamo_antiguo_estado' => 0,
        'estado_retanqueo' => 'solicitud_pendiente',
    ]);

    $clientes = \App\Models\Cliente::factory()->count(3)->create();

    // 2 de 3 integrantes originales retanquean -> 66.6% >= 51%.
    \App\Models\RetanqueoIndividual::create([
        'retanqueo_id' => $retanqueo->id,
        'cliente_id' => $clientes[0]->id,
        'participacion_tipo' => 'retanquea',
        'aporte_cobertura' => 0,
        'monto_solicitado' => 500,
        'monto_desembolsar' => 500,
        'aceptacion_cliente' => 0,
        'estado_retanqueo_individual' => 'propuesto',
    ]);
    \App\Models\RetanqueoIndividual::create([
        'retanqueo_id' => $retanqueo->id,
        'cliente_id' => $clientes[1]->id,
        'participacion_tipo' => 'retanquea',
        'aporte_cobertura' => 0,
        'monto_solicitado' => 500,
        'monto_desembolsar' => 500,
        'aceptacion_cliente' => 0,
        'estado_retanqueo_individual' => 'propuesto',
    ]);
    \App\Models\RetanqueoIndividual::create([
        'retanqueo_id' => $retanqueo->id,
        'cliente_id' => $clientes[2]->id,
        'participacion_tipo' => 'no_retanquea',
        'aporte_cobertura' => 0,
        'monto_solicitado' => 0,
        'monto_desembolsar' => 0,
        'aceptacion_cliente' => 0,
        'estado_retanqueo_individual' => 'propuesto',
    ]);

    $service = app(RetanqueoWorkflowInterface::class);

    $resultado = $service->aprobarRetanqueo($retanqueo->id);

    expect($resultado->estado_retanqueo)->toBe('aprobado');
});
