<?php

use Tests\TestCase;
use App\Models\Prestamo;
use App\Models\CuotaIndividual;
use App\Models\Pago;
use App\Models\AplicacionPago;
use App\Models\Ingreso;
use App\Domain\Pagos\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('PagoService - Canonical Payment Path', function () {
    it('registrarCanonico crea un Pago y AplicacionPago sin usar cuota_grupal_id', function () {
        // Preparar
        $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);
        $cuotaIndividual = CuotaIndividual::factory()->create([
            'prestamo_id' => $prestamo->id,
            'numero_cuota' => 1,
            'saldo_capital' => 100,
            'saldo_interes' => 20,
            'estado' => 'pendiente'
        ]);

        $service = new PagoService();

        // Actuar
        $pago = $service->registrarCanonico(
            prestamo: $prestamo,
            montoPagado: '120.00',
            codigoOperacion: 'OP-123',
            tipoPago: 'efectivo',
            observaciones: 'Pago canónico de prueba'
        );

        // Verificar
        expect($pago)->toBeInstanceOf(Pago::class);
        expect($pago->cuota_grupal_id)->toBeNull();
        expect($pago->monto_pagado)->toEqual(120.00);
        expect($pago->estado_pago)->toBe('aprobado');
        expect($pago->codigo_operacion)->toBe('OP-123');

        $aplicaciones = AplicacionPago::where('pago_id', $pago->id)->get();
        expect($aplicaciones)->toHaveCount(1);
        expect($aplicaciones->first()->cuota_id)->toBe($cuotaIndividual->id);
        expect($aplicaciones->first()->monto_aplicado_capital)->toEqual(100.00);
        expect($aplicaciones->first()->monto_aplicado_interes)->toEqual(20.00);

        // Verificar saldos
        expect($cuotaIndividual->fresh()->saldo_capital)->toEqual(0.00);
        expect($cuotaIndividual->fresh()->saldo_interes)->toEqual(0.00);
        expect($cuotaIndividual->fresh()->estado)->toBe('pagada');

        // Verificar ingreso
        expect(Ingreso::where('pago_id', $pago->id)->exists())->toBeTrue();
    });

    it('registrarCanonico arrastra el remanente de una cuota a la siguiente (Eje 3 — waterfall consolidado)', function () {
        // Preparar: dos cuotas individuales pendientes, en orden de numero_cuota.
        $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);
        $cuota1 = CuotaIndividual::factory()->create([
            'prestamo_id' => $prestamo->id,
            'numero_cuota' => 1,
            'saldo_capital' => 50,
            'saldo_interes' => 10,
            'estado' => 'pendiente',
        ]);
        $cuota2 = CuotaIndividual::factory()->create([
            'prestamo_id' => $prestamo->id,
            'numero_cuota' => 2,
            'saldo_capital' => 80,
            'saldo_interes' => 20,
            'estado' => 'pendiente',
        ]);

        $service = new PagoService();

        // Actuar: 150 alcanza para cancelar cuota1 (60) y dejar 90 para cuota2 (interés 20 + capital 70).
        $pago = $service->registrarCanonico(
            prestamo: $prestamo,
            montoPagado: '150.00',
        );

        // Verificar: cuota1 cancelada por completo.
        expect($cuota1->fresh()->saldo_capital)->toEqual(0.00);
        expect($cuota1->fresh()->saldo_interes)->toEqual(0.00);
        expect($cuota1->fresh()->estado)->toBe('pagada');

        // Verificar: cuota2 recibe el remanente — interés cubierto, capital parcial.
        expect($cuota2->fresh()->saldo_interes)->toEqual(0.00);
        expect($cuota2->fresh()->saldo_capital)->toEqual(10.00);
        expect($cuota2->fresh()->estado)->toBe('pendiente');

        $aplicaciones = AplicacionPago::where('pago_id', $pago->id)->orderBy('cuota_id')->get();
        expect($aplicaciones)->toHaveCount(2);
        expect($aplicaciones->first()->monto_aplicado_capital)->toEqual(50.00);
        expect($aplicaciones->first()->monto_aplicado_interes)->toEqual(10.00);
        expect($aplicaciones->last()->monto_aplicado_capital)->toEqual(70.00);
        expect($aplicaciones->last()->monto_aplicado_interes)->toEqual(20.00);
    });
});
