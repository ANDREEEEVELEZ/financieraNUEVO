<?php

use Tests\TestCase;
use App\Models\Pago;
use App\Models\CuotasGrupales;
use App\Models\Prestamo;
use App\Models\Grupo;
use App\Models\Mora;
use App\Models\Ingreso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);
uses(Tests\TestCase::class);
describe('Relaciones y lógica de pagos', function ()
{
    it('verifica que un pago esta asociado a una cuota', function ()
    {
        $prestamo = Prestamo::factory()->create
        ([
            'estado' => 'aprobado',
        ]);

        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
        ]);

        $pago = Pago::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'monto_pagado' => 100,
        ]);

        expect($pago->cuotaGrupal)->toBeInstanceOf(CuotasGrupales::class);
        expect($pago->cuotaGrupal->id)->toBe($cuota->id);
    });

    it('verifica si un pago cubre totalmente la cuota', function ()
    {
        $prestamo = Prestamo::factory()->create
        ([
            'estado' => 'aprobado',
        ]);

        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
            'monto_cuota_grupal' => 150,
            'saldo_pendiente' => 150,
        ]);

        $pago = Pago::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'monto_pagado' => 150,
            'tipo_pago' => 'pago_completo',
        ]);

        $estaPagado = $pago->monto_pagado >= $cuota->monto_cuota_grupal;

        expect($estaPagado)->toBeTrue();
    });

    it('detecta que el pago no cubre totalmente la cuota', function ()
    {
        $prestamo = Prestamo::factory()->create
        ([
            'estado' => 'aprobado',
        ]);

        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
            'monto_cuota_grupal' => 200,
            'saldo_pendiente' => 200,
        ]);

        $pago = Pago::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'monto_pagado' => 150,
            'tipo_pago' => 'pago_parcial',
        ]);

        $estaPagado = $pago->monto_pagado >= $cuota->monto_cuota_grupal;

        expect($estaPagado)->toBeFalse();
    });
});
describe('Validaciones de creación de pagos', function ()
{
    it('permite crear pagos solo para préstamos aprobados', function ()
    {
        $prestamo = Prestamo::factory()->create
        ([
            'estado' => 'aprobado',
        ]);

        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
        ]);

        $pago = Pago::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'monto_pagado' => 100,
        ]);

        expect($pago)->toBeInstanceOf(Pago::class);
        expect($pago->cuotaGrupal->prestamo->estado)->toBe('aprobado');
    });

    it('establece pago con estado pendiente por defecto', function ()
    {
        $prestamo = Prestamo::factory()->create(['estado' => 'aprobado']);
        $cuota = CuotasGrupales::factory()->create(['prestamo_id' => $prestamo->id]);

        $pago = Pago::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'monto_pagado' => 100,
        ]);

        expect($pago->estado_pago)->toBe('pendiente');
    });
});

describe('Funcionalidad de aprobación de pagos', function ()
{
    it('aprueba un pago completo correctamente', function ()
    {
        $prestamo = Prestamo::factory()->create(['estado' => 'aprobado']);
        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
            'monto_cuota_grupal' => 150,
            'saldo_pendiente' => 150,
            'estado_pago' => 'pendiente',
            'estado_cuota_grupal' => 'vigente',
        ]);
        $pago = Pago::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'monto_pagado' => 150,
            'tipo_pago' => 'pago_completo',
            'estado_pago' => 'pendiente',
        ]);
        $pago->aprobar();
        expect($pago->fresh()->estado_pago)->toBe('aprobado');
        expect($cuota->fresh()->saldo_pendiente)->toEqual(0.00);
        expect($cuota->fresh()->estado_pago)->toBe('pagado');
        expect($cuota->fresh()->estado_cuota_grupal)->toBe('cancelada');
    });

    it('aprueba un pago parcial correctamente', function ()
    {
        $prestamo = Prestamo::factory()->create(['estado' => 'aprobado']);

        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
            'monto_cuota_grupal' => 200,
            'saldo_pendiente' => 200,
            'estado_pago' => 'pendiente',
            'estado_cuota_grupal' => 'vigente',
        ]);

        $pago = Pago::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'monto_pagado' => 100,
            'tipo_pago' => 'pago_parcial',
            'estado_pago' => 'pendiente',
        ]);

        $pago->aprobar();
        expect($pago->fresh()->estado_pago)->toBe('aprobado');
        expect($cuota->fresh()->saldo_pendiente)->toEqual(100.00);
        expect($cuota->fresh()->estado_pago)->toBe('parcial');
        expect($cuota->fresh()->estado_cuota_grupal)->toBe('vigente');
    });

    it('maneja pagos con mora correctamente', function ()
    {
        $prestamo = Prestamo::factory()->create(['estado' => 'aprobado']);

        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
            'monto_cuota_grupal' => 150,
            'saldo_pendiente' => 150,
            'estado_cuota_grupal' => 'mora',
        ]);
        $mora = Mora::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'estado_mora' => 'pendiente',
            'fecha_atraso' => now()->subDays(5),
        ]);

        $montoMora = $mora->monto_mora_calculado;
        $montoTotal = $cuota->monto_cuota_grupal + $montoMora;

        $pago = Pago::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'tipo_pago' => 'pago_completo',
            'monto_pagado' => $montoTotal,
            'monto_mora_pagada' => $montoMora,
        ]);
        $pago->aprobar();
        expect($pago->fresh()->estado_pago)->toBe('aprobado');
        expect($cuota->fresh()->saldo_pendiente)->toEqual(0.00);
        expect($cuota->fresh()->estado_cuota_grupal)->toBe('cancelada');
        expect($mora->fresh()->estado_mora)->toBe('pagada');
    });


});

describe('Funcionalidad de rechazo de pagos', function ()
{
    it('recalcula saldo cuando se rechaza un pago con otros pagos válidos', function ()
    {
        $prestamo = Prestamo::factory()->create(['estado' => 'aprobado']);

        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
            'monto_cuota_grupal' => 200,
            'saldo_pendiente' => 100,
            'estado_pago' => 'parcial',
        ]);
        $pagoAprobado = Pago::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'monto_pagado' => 100,
            'estado_pago' => 'Aprobado',
        ]);
        $pagoARechazar = Pago::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'monto_pagado' => 50,
            'estado_pago' => 'pendiente',
        ]);
        $pagoARechazar->rechazar();
        expect($pagoARechazar->fresh()->estado_pago)->toBe('Rechazado');
        expect($cuota->fresh()->saldo_pendiente)->toBe('100.00');
        expect($cuota->fresh()->estado_pago)->toBe('parcial');
    });


});


