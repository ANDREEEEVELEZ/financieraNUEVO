<?php

use Tests\TestCase;
use App\Contracts\SaldoCuotaServiceInterface;
use App\Models\Pago;
use App\Models\CuotasGrupales;
use App\Models\Prestamo;
use App\Models\Grupo;
use App\Models\Mora;
use App\Models\Ingreso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);
uses(Tests\TestCase::class);
describe('Relaciones y lógica de pagos', function ()
{
    it('verifica que un pago esta asociado a una cuota', function ()
    {
        $prestamo = Prestamo::factory()->create
        ([
            'estado' => Prestamo::ESTADO_ACTIVO,
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
            'estado' => Prestamo::ESTADO_ACTIVO,
        ]);

        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
            'monto_cuota_grupal' => 150,
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
            'estado' => Prestamo::ESTADO_ACTIVO,
        ]);

        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
            'monto_cuota_grupal' => 200,
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
            'estado' => Prestamo::ESTADO_ACTIVO,
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
        expect($pago->cuotaGrupal->prestamo->estado)->toBe(Prestamo::ESTADO_ACTIVO);
    });

    it('establece pago con estado pendiente por defecto', function ()
    {
        $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);
        $cuota = CuotasGrupales::factory()->create(['prestamo_id' => $prestamo->id]);

        $pago = Pago::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'monto_pagado' => 100,
        ]);

        expect($pago->estado_pago)->toBe('pendiente');
    });

    it('permite crear pagos con cuota_grupal_id nulo (pago canónico)', function () {
        $pago = Pago::factory()->create([
            'cuota_grupal_id' => null,
            'monto_pagado' => 100,
            'estado_pago' => 'pendiente',
        ]);

        expect($pago)->toBeInstanceOf(Pago::class);
        expect($pago->cuota_grupal_id)->toBeNull();
    });
});

describe('Funcionalidad de aprobación de pagos', function ()
{
    beforeEach(fn () => Auth::login(User::factory()->create()));

    it('aprueba un pago completo correctamente', function ()
    {
        $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);
        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
            'monto_cuota_grupal' => 150,
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
        expect(app(SaldoCuotaServiceInterface::class)->saldoTotal($cuota->fresh()))->toBe('0.00');
        expect($cuota->fresh()->estado_pago)->toBe('pagado');
        expect($cuota->fresh()->estado_cuota_grupal)->toBe('cancelada');
    });

    it('aprueba un pago parcial correctamente', function ()
    {
        $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);

        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
            'monto_cuota_grupal' => 200,
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
        expect(app(SaldoCuotaServiceInterface::class)->saldoTotal($cuota->fresh()))->toBe('100.00');
        expect($cuota->fresh()->estado_pago)->toBe('parcial');
        expect($cuota->fresh()->estado_cuota_grupal)->toBe('vigente');
    });

    it('maneja pagos con mora correctamente', function ()
    {
        $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);

        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
            'monto_cuota_grupal' => 150,
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
        expect(app(SaldoCuotaServiceInterface::class)->saldoTotal($cuota->fresh()))->toBe('0.00');
        expect($cuota->fresh()->estado_cuota_grupal)->toBe('cancelada');
        expect($mora->fresh()->estado_mora)->toBe('pagada');
    });


});

describe('Funcionalidad de rechazo de pagos', function ()
{
    it('recalcula saldo cuando se rechaza un pago con otros pagos válidos', function ()
    {
        $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);

        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
            'monto_cuota_grupal' => 200,
            'estado_pago' => 'parcial',
        ]);
        $pagoAprobado = Pago::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'monto_pagado' => 100,
            // Pinned (no aleatorio): SaldoCuotaService neta monto_mora_pagada contra
            // el pago antes de aplicar el resto a capital (waterfall mora-primero);
            // el default del factory es aleatorio (0-50) y este escenario es
            // deliberadamente "sin mora".
            'monto_mora_pagada' => 0,
            'estado_pago' => 'Aprobado',
        ]);
        $pagoARechazar = Pago::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'monto_pagado' => 50,
            'monto_mora_pagada' => 0,
            'estado_pago' => 'pendiente',
        ]);
        $pagoARechazar->rechazar();
        expect($pagoARechazar->fresh()->estado_pago)->toBe('rechazado');
        expect(app(SaldoCuotaServiceInterface::class)->saldoTotal($cuota->fresh()))->toBe('100.00');
        expect($cuota->fresh()->estado_pago)->toBe('parcial');
    });


});

describe('Regresión: casing de estado_pago', function ()
{
    beforeEach(fn () => Auth::login(User::factory()->create()));

    it('normaliza estado_pago a minúsculas sin importar el casing de entrada', function ()
    {
        $pago = Pago::factory()->create([
            'cuota_grupal_id' => null,
            'estado_pago' => 'PENDIENTE',
        ]);

        expect($pago->estado_pago)->toBe('pendiente');

        $pago->estado_pago = 'APROBADO';
        $pago->save();

        expect($pago->fresh()->estado_pago)->toBe('aprobado');
    });

    it('aprobarPago no ignora silenciosamente un pago creado con estado_pago en mayúsculas', function ()
    {
        $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);
        $cuota = CuotasGrupales::factory()->create([
            'prestamo_id' => $prestamo->id,
            'monto_cuota_grupal' => 150,
            'estado_pago' => 'pendiente',
            'estado_cuota_grupal' => 'vigente',
        ]);
        $pago = Pago::factory()->create([
            'cuota_grupal_id' => $cuota->id,
            'monto_pagado' => 150,
            'tipo_pago' => 'pago_completo',
            'estado_pago' => 'Pendiente',
        ]);

        $pago->aprobar();

        expect($pago->fresh()->estado_pago)->toBe('aprobado');
        expect(app(SaldoCuotaServiceInterface::class)->saldoTotal($cuota->fresh()))->toBe('0.00');
    });

    it('la migración de normalización permite que los filtros en memoria detecten filas legacy con casing capitalizado', function ()
    {
        // Filament (ListPagos, GrupoDetallePagos) filtra la relación `pagos` ya cargada en memoria
        // con Collection::where(), que hace comparación estricta de PHP y no se beneficia del
        // collation case-insensitive de MySQL como sí lo hacen las consultas SQL.
        $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);
        $cuota = CuotasGrupales::factory()->create([
            'prestamo_id' => $prestamo->id,
            'monto_cuota_grupal' => 200,
        ]);

        // Simula una fila legacy insertada antes del fix, con estado_pago capitalizado (sin pasar por el mutator).
        DB::table('pagos')->insert([
            'cuota_grupal_id' => $cuota->id,
            'tipo_pago' => 'pago_completo',
            'monto_pagado' => 200,
            'estado_pago' => 'Aprobado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($cuota->fresh()->pagos->where('estado_pago', 'aprobado')->count())->toBe(0);

        // Backfill: misma lógica que la migración 2026_07_06_053703_normalize_pagos_estado_pago_casing.
        DB::table('pagos')
            ->whereRaw('BINARY estado_pago != LOWER(estado_pago)')
            ->update(['estado_pago' => DB::raw('LOWER(estado_pago)')]);

        expect($cuota->fresh()->pagos->where('estado_pago', 'aprobado')->count())->toBe(1);
    });
});


