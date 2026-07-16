<?php

use App\Contracts\RetanqueoEjecucionInterface;
use App\Contracts\SaldoCuotaServiceInterface;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Models\Retanqueo;
use App\Models\RetanqueoIndividual;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Task 3.7 — RetanqueoEjecucionService::actualizarPrestamoAntiguo() rewritten
 * as a ledger operation (Req 1 Scenario 1.1/1.2/1.3, decision D6).
 *
 * Fixture mirrors the reported regression scenario: S/100 cuota grupal,
 * S/60 covered by a client who retanquea -> saldo S/40 everywhere.
 */
function makeRetanqueoAprobadoConCuotaPendiente(): array
{
    $grupo = Grupo::factory()->create();
    $prestamoAntiguo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => Prestamo::ESTADO_ACTIVO,
    ]);

    $cliente = Cliente::factory()->create();
    $grupo->clientes()->attach($cliente->id, [
        'fecha_ingreso' => now()->toDateString(),
        'estado_grupo_cliente' => 'activo',
    ]);

    PrestamoIndividual::factory()->create([
        'prestamo_id' => $prestamoAntiguo->id,
        'cliente_id' => $cliente->id,
        'monto_cuota_prestamo_individual' => 60,
        'estado' => 'Aprobado',
    ]);

    $cuotaGrupal = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamoAntiguo->id,
        'numero_cuota' => 1,
        'monto_cuota_grupal' => 100,
        // Valor legacy deliberadamente erroneo: actualizarPrestamoAntiguo() ya
        // NO debe escribir esta columna (Req 1 Scenario 1.1/1.3) — si sigue en
        // este valor tras ejecutar, la mutacion directa quedo eliminada.
        'saldo_pendiente' => 999999.99,
        'estado_pago' => 'pendiente',
        'estado_cuota_grupal' => 'vigente',
    ]);

    CuotaIndividual::factory()->create([
        'prestamo_id' => $prestamoAntiguo->id,
        'cliente_id' => $cliente->id,
        'numero_cuota' => 1,
        'saldo_capital' => 80,
        'saldo_interes' => 20,
        'estado' => 'pendiente',
    ]);

    $retanqueo = Retanqueo::create([
        'prestamo_id' => $prestamoAntiguo->id,
        'prestamo_nuevo_id' => null,
        'monto_retanqueo' => 500,
        'monto_usado_para_cubrir_antiguo' => 60,
        'monto_desembolsar' => 440,
        'monto_cuota' => null,
        'cantidad_cuotas_nuevo' => 4,
        'saldo_restante_prestamo_antiguo' => 0,
        'prestamo_antiguo_estado' => 0,
        'estado_retanqueo' => 'aprobado',
        'fecha_aceptacion' => now(),
    ]);

    RetanqueoIndividual::create([
        'retanqueo_id' => $retanqueo->id,
        'cliente_id' => $cliente->id,
        'participacion_tipo' => 'retanquea',
        'aporte_cobertura' => 60,
        // monto_solicitado must match one of RetanqueoEjecucionService::calcularSeguro's accepted brackets.
        'monto_solicitado' => 500,
        'monto_desembolsar' => 440,
        'aceptacion_cliente' => 1,
        'estado_retanqueo_individual' => 'aceptado',
    ]);

    return [$retanqueo, $prestamoAntiguo, $cuotaGrupal, $cliente];
}

it('creates a retanqueo Pago ledger row for the covered cuota instead of mutating saldo_pendiente', function () {
    [$retanqueo, $prestamoAntiguo, $cuotaGrupal] = makeRetanqueoAprobadoConCuotaPendiente();

    app(RetanqueoEjecucionInterface::class)->ejecutarRetanqueo($retanqueo->id);

    $pago = Pago::where('cuota_grupal_id', $cuotaGrupal->id)->where('origen_pago', 'retanqueo')->first();

    expect($pago)->not->toBeNull();
    expect($pago->estado_pago)->toBe('aprobado');
    expect((float) $pago->monto_pagado)->toEqual(60.0);

    // Requirement 1 Scenario 1.1/1.3: zero direct saldo_pendiente mutation —
    // the legacy sentinel value seeded above must remain untouched.
    expect((float) $cuotaGrupal->fresh()->saldo_pendiente)->toEqual(999999.99);
});

it('SaldoCuotaService reflects the retanqueo coverage automatically (S/100 cuota, S/60 covered -> saldo S/40)', function () {
    [$retanqueo, $prestamoAntiguo, $cuotaGrupal] = makeRetanqueoAprobadoConCuotaPendiente();

    app(RetanqueoEjecucionInterface::class)->ejecutarRetanqueo($retanqueo->id);

    $saldo = app(SaldoCuotaServiceInterface::class)->saldoTotal($cuotaGrupal->fresh());

    expect($saldo)->toBe('40.00');
});

it('recalculates saldo_restante_prestamo_antiguo from the ledger, not from the stale column', function () {
    [$retanqueo, $prestamoAntiguo, $cuotaGrupal] = makeRetanqueoAprobadoConCuotaPendiente();

    app(RetanqueoEjecucionInterface::class)->ejecutarRetanqueo($retanqueo->id);

    expect((float) $retanqueo->fresh()->saldo_restante_prestamo_antiguo)->toEqual(40.0);
});

it('does not create an Ingreso row for the retanqueo coverage pago', function () {
    [$retanqueo, $prestamoAntiguo, $cuotaGrupal] = makeRetanqueoAprobadoConCuotaPendiente();

    app(RetanqueoEjecucionInterface::class)->ejecutarRetanqueo($retanqueo->id);

    $pago = Pago::where('cuota_grupal_id', $cuotaGrupal->id)->where('origen_pago', 'retanqueo')->first();

    expect($pago->ingreso)->toBeNull();
});
