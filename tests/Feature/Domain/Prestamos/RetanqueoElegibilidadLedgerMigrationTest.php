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
 * legacy `saldo_pendiente` a derivar el saldo del ledger
 * (SaldoCuotaService). Estos tests seedean deliberadamente una columna
 * `saldo_pendiente` MENTIROSA (divergente del ledger real) para probar que
 * el resultado de elegibilidad sigue la VERDAD del ledger, no el valor
 * legacy — exactamente el escenario que motiva el drop de la columna.
 */
it('ElegibilidadRetanqueoIndividual sigue siendo elegible cuando la columna legacy dice 0 pero el ledger dice saldo pendiente', function () {
    $prestamo = Prestamo::factory()->create(['estado' => 'Aprobado']);

    // Columna legacy MIENTE: dice 0 (pagado), pero no hay Pago aprobado alguno
    // -> el ledger real dice monto_cuota_grupal completo pendiente.
    CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 200,
        'saldo_pendiente' => 0,
        'estado_pago' => 'pendiente',
    ]);

    $strategy = new ElegibilidadRetanqueoIndividual;

    expect($strategy->esElegible($prestamo))->toBeTrue();
});

it('ElegibilidadRetanqueoIndividual NO es elegible cuando la columna legacy dice pendiente pero el ledger dice pagado', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);

    // Columna legacy MIENTE: dice 999999.99 (deuda), pero el ledger real dice
    // 0.00 porque hay un Pago aprobado que cubre el monto completo.
    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 200,
        'saldo_pendiente' => 999999.99,
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

it('ElegibilidadRetanqueoGrupal (rama fallback, sin producto financiero) sigue la misma verdad del ledger', function () {
    $prestamo = Prestamo::factory()->create(['estado' => 'Aprobado', 'grupo_id' => null]);

    CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 150,
        'saldo_pendiente' => 0, // legacy miente
        'estado_pago' => 'pendiente',
    ]);

    $strategy = new ElegibilidadRetanqueoGrupal;

    expect($strategy->esElegible($prestamo))->toBeTrue();
});

it('RetanqueoQueryService::obtenerGruposElegibles refleja el ledger, no la columna legacy divergente', function () {
    $service = app(RetanqueoQueryInterface::class);

    $asesor = Asesor::factory()->create();
    $grupo = Grupo::factory()->create(['asesor_id' => $asesor->id, 'estado_grupo' => 'Activo']);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => 'Aprobado',
        'cantidad_cuotas' => 4,
    ]);

    // 3 cuotas pagadas (excluidas por estado_pago, columna legacy irrelevante aquí).
    CuotasGrupales::factory()->count(3)->create([
        'prestamo_id' => $prestamo->id,
        'estado_pago' => 'pagado',
        'saldo_pendiente' => 0,
    ]);

    // 1 cuota pendiente cuya columna legacy dice 0 (pagada) pero el ledger real
    // dice pendiente (sin Pago aprobado alguno).
    CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'monto_cuota_grupal' => 100,
        'estado_pago' => 'pendiente',
        'saldo_pendiente' => 0,
    ]);

    $result = $service->obtenerGruposElegibles();

    expect($result->count())->toBe(1);
    expect($result->first()->id)->toBe($grupo->id);
});

it('RetanqueoWorkflowService::aprobarRetanqueo bloquea cuando el ledger dice 2+ cuotas pendientes aunque la columna legacy diga lo contrario', function () {
    $grupo = Grupo::factory()->create();
    $prestamoAntiguo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => 'Aprobado',
    ]);

    // 2 cuotas pendientes reales (sin Pago), aunque la columna legacy diga 0
    // (como si ya estuvieran pagadas) en ambas.
    CuotasGrupales::factory()->count(2)->create([
        'prestamo_id' => $prestamoAntiguo->id,
        'monto_cuota_grupal' => 100,
        'estado_pago' => 'pendiente',
        'saldo_pendiente' => 0,
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
