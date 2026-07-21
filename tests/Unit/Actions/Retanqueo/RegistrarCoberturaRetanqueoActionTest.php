<?php

use App\Actions\Retanqueo\RegistrarCoberturaRetanqueoAction;
use App\Contracts\SaldoCuotaServiceInterface;
use App\Models\AplicacionPago;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Ingreso;
use App\Models\Pago;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Task 3.4 — RegistrarCoberturaRetanqueoAction (Req 1 Scenario 1.1/1.2/1.4, decision D6).
 *
 * Fixture mirrors the reported regression scenario: S/100 cuota, S/60 covered
 * by retanqueo -> saldo S/40 (SaldoCuotaService, ledger-derived, no special case).
 */
function makeCuotaGrupalConCuotaIndividual(
    float $montoCuotaGrupal = 100,
    float $saldoCapital = 80,
    float $saldoInteres = 20
): CuotasGrupales {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);

    $cuotaGrupal = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'numero_cuota' => 1,
        'monto_cuota_grupal' => $montoCuotaGrupal,
        'estado_pago' => 'pendiente',
        'estado_cuota_grupal' => 'vigente',
    ]);

    CuotaIndividual::factory()->create([
        'prestamo_id' => $prestamo->id,
        'numero_cuota' => 1,
        'saldo_capital' => $saldoCapital,
        'saldo_interes' => $saldoInteres,
        'estado' => 'pendiente',
    ]);

    return $cuotaGrupal->fresh();
}

it('creates a Pago with origen_pago retanqueo and estado_pago aprobado', function () {
    $cuotaGrupal = makeCuotaGrupalConCuotaIndividual();

    $pago = app(RegistrarCoberturaRetanqueoAction::class)($cuotaGrupal, '60.00');

    expect($pago)->toBeInstanceOf(Pago::class);
    expect($pago->fresh())
        ->origen_pago->toBe('retanqueo')
        ->estado_pago->toBe('aprobado')
        ->monto_pagado->toEqual(60.00)
        ->monto_mora_pagada->toEqual(0.00);
});

it('creates AplicacionPago rows tagged tipo_aplicacion retanqueo with interes-first waterfall', function () {
    $cuotaGrupal = makeCuotaGrupalConCuotaIndividual(montoCuotaGrupal: 100, saldoCapital: 80, saldoInteres: 20);

    $pago = app(RegistrarCoberturaRetanqueoAction::class)($cuotaGrupal, '60.00');

    $aplicaciones = AplicacionPago::where('pago_id', $pago->id)->get();

    expect($aplicaciones)->toHaveCount(1);
    expect($aplicaciones->first())
        ->tipo_aplicacion->toBe('retanqueo')
        ->monto_aplicado_interes->toEqual(20.00) // saldo_interes cubierto primero
        ->monto_aplicado_capital->toEqual(40.00) // resto (60 - 20) va a capital
        ->monto_aplicado_mora->toEqual(0.00);    // mora bucket queda en 0.00 (decision D2)
});

it('does NOT create an Ingreso row for retanqueo coverage (decision #241 pt.2)', function () {
    $cuotaGrupal = makeCuotaGrupalConCuotaIndividual();

    $pago = app(RegistrarCoberturaRetanqueoAction::class)($cuotaGrupal, '60.00');

    expect(Ingreso::where('pago_id', $pago->id)->exists())->toBeFalse();
});

it('SaldoCuotaService reflects the coverage automatically (S/100 cuota, S/60 covered -> saldo S/40)', function () {
    $cuotaGrupal = makeCuotaGrupalConCuotaIndividual(montoCuotaGrupal: 100, saldoCapital: 80, saldoInteres: 20);

    app(RegistrarCoberturaRetanqueoAction::class)($cuotaGrupal, '60.00');

    $saldo = app(SaldoCuotaServiceInterface::class)->saldoTotal($cuotaGrupal->fresh());

    expect($saldo)->toBe('40.00');
});

it('marks the cuota as pagado/cancelada when coverage fully settles the saldo', function () {
    $cuotaGrupal = makeCuotaGrupalConCuotaIndividual(montoCuotaGrupal: 60, saldoCapital: 40, saldoInteres: 20);

    app(RegistrarCoberturaRetanqueoAction::class)($cuotaGrupal, '60.00');

    $cuotaGrupal = $cuotaGrupal->fresh();

    expect($cuotaGrupal->estado_pago)->toBe('pagado');
    expect($cuotaGrupal->estado_cuota_grupal)->toBe('cancelada');
});

it('leaves the cuota as parcial/vigente when coverage does not fully settle the saldo', function () {
    $cuotaGrupal = makeCuotaGrupalConCuotaIndividual(montoCuotaGrupal: 100, saldoCapital: 80, saldoInteres: 20);

    app(RegistrarCoberturaRetanqueoAction::class)($cuotaGrupal, '60.00');

    $cuotaGrupal = $cuotaGrupal->fresh();

    expect($cuotaGrupal->estado_pago)->toBe('parcial');
    expect($cuotaGrupal->estado_cuota_grupal)->toBe('vigente');
});

it('throws when the coverage amount is zero or negative', function () {
    $cuotaGrupal = makeCuotaGrupalConCuotaIndividual();

    expect(fn () => app(RegistrarCoberturaRetanqueoAction::class)($cuotaGrupal, '0.00'))
        ->toThrow(InvalidArgumentException::class);
});

it('dispatches CoberturaRetanqueoRegistrada after commit', function () {
    Event::fake([\App\Events\Domain\CoberturaRetanqueoRegistrada::class]);

    $cuotaGrupal = makeCuotaGrupalConCuotaIndividual();

    $pago = app(RegistrarCoberturaRetanqueoAction::class)($cuotaGrupal, '60.00');

    Event::assertDispatched(\App\Events\Domain\CoberturaRetanqueoRegistrada::class, function ($event) use ($pago) {
        return $event->pago->id === $pago->id;
    });
});
