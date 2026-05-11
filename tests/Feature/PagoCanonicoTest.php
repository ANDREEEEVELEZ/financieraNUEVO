<?php

use Tests\TestCase;
use App\Models\Prestamo;
use App\Models\CuotaIndividual;
use App\Models\Pago;
use App\Models\AplicacionPago;
use App\Models\Ingreso;
use App\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);
uses(TestCase::class);

describe('PagoService - Canonical Payment Path', function () {
    it('registrarCanonico crea un Pago y AplicacionPago sin usar cuota_grupal_id', function () {
        // Preparar
        $prestamo = Prestamo::factory()->create(['estado' => 'aprobado']);
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
});
