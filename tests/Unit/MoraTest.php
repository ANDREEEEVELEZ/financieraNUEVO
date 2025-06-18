<?php

use App\Models\Mora;
use App\Models\CuotasGrupales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Grupo;
use App\Models\Cliente;
use App\Models\Prestamo;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Mora', function () {
    it('verifica que una mora esté asociada a una cuota grupal', function ()
    {
        $cuota = CuotasGrupales::factory()->create();
        $mora = Mora::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
        ]);
        expect($mora->cuotaGrupal)->toBeInstanceOf(CuotasGrupales::class);
    });

    it('calcula correctamente el monto de mora pendiente', function ()
    {
        $grupo = Grupo::factory()->create();
        $clientes = Cliente::factory()->count(3)->create();
        $grupo->clientes()->attach($clientes->pluck('id'),
        [
            'fecha_ingreso' => now(),
            'rol' => 'miembro',
            'estado_grupo_cliente' => 'activo',
        ]);
        $prestamo = Prestamo::factory()->create
        ([
            'grupo_id' => $grupo->id,
        ]);
        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
            'fecha_vencimiento' => now()->subDays(5),
        ]);
        $mora = Mora::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'estado_mora' => 'pendiente',
            'fecha_atraso' => now()->subDays(1),
        ]);
        $mora->refresh();
        $mora->update(['fecha_atraso' => now()]);
        $mora->refresh();
        $montoEsperado = 3 * 4;
        expect($mora->monto_mora_calculado)->toEqual($montoEsperado);
    });

    it('no recalcula monto de mora si está pagada', function ()
    {
        $grupo = Grupo::factory()->create();
        $clientes = Cliente::factory()->count(2)->create();

        $grupo->clientes()->attach($clientes->pluck('id'),
         [
            'fecha_ingreso' => now(),
            'rol' => 'miembro',
            'estado_grupo_cliente' => 'activo',
        ]);
        $prestamo = Prestamo::factory()->create
        ([
            'grupo_id' => $grupo->id,
        ]);
        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
            'fecha_vencimiento' => now()->subDays(2),
        ]);
        $mora = Mora::factory()->create([
            'cuota_grupal_id' => $cuota->id,
            'fecha_atraso' => now(),
            'estado_mora' => 'pagada',
        ]);
        $mora->refresh();
        $montoEsperado = 2 * 1;
        expect($mora->monto_mora_calculado)->toEqual($montoEsperado);
    });

    it('no actualiza fecha de atraso si la mora está pagada', function ()
    {
        $grupo = Grupo::factory()->create();
        $clientes = Cliente::factory()->count(2)->create();
        $grupo->clientes()->attach($clientes->pluck('id'),
        [
            'fecha_ingreso' => now(),
            'rol' => 'miembro',
            'estado_grupo_cliente' => 'activo',
        ]);
        $prestamo = Prestamo::factory()->create
        ([
            'grupo_id' => $grupo->id,
        ]);
        $cuota = CuotasGrupales::factory()->create
        ([
            'prestamo_id' => $prestamo->id,
            'fecha_vencimiento' => now()->subDays(5),
        ]);
        $mora = Mora::factory()->create
        ([
            'cuota_grupal_id' => $cuota->id,
            'estado_mora' => 'pagada',
            'fecha_atraso' => now()->subDays(2),
        ]);
        $original = $mora->fecha_atraso;
        $mora->actualizarDiasAtraso();
        expect($mora->fresh()->fecha_atraso)->toEqual($original);
    });



});
