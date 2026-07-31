<?php

use App\Models\Mora;
use App\Models\CuotasGrupales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Grupo;
use App\Models\Cliente;
use App\Models\Prestamo;
use App\Models\ProductoFinanciero;
use App\Domain\Mora\Strategies\MoraFlatPorIntegranteStrategy;
use App\Domain\Mora\Strategies\MoraPorcentualSobreSaldoStrategy;

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
        $grupo = Grupo::factory()->create(['numero_integrantes' => 3]);
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
        $grupo = Grupo::factory()->create(['numero_integrantes' => 2]);
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
        $grupo = Grupo::factory()->create(['numero_integrantes' => 2]);
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

    // Eje 2 (dominio-pagos-mora-retanqueo) — delegación a MoraCalculationStrategy.
    describe('delegación a MoraCalculationStrategy (Eje 2)', function () {
        it('ProductoFinanciero::moraStrategy() cae en flat para tipo grupal sin tipo_calculo_mora seteado', function () {
            $producto = ProductoFinanciero::factory()->create([
                'tipo' => 'grupal',
                'tipo_calculo_mora' => null,
            ]);

            expect($producto->moraStrategy())->toBeInstanceOf(MoraFlatPorIntegranteStrategy::class);
        });

        it('ProductoFinanciero::moraStrategy() cae en porcentual para tipo individual sin tipo_calculo_mora seteado', function () {
            $producto = ProductoFinanciero::factory()->create([
                'tipo' => 'individual',
                'tipo_calculo_mora' => null,
            ]);

            expect($producto->moraStrategy())->toBeInstanceOf(MoraPorcentualSobreSaldoStrategy::class);
        });

        it('ProductoFinanciero::moraStrategy() respeta tipo_calculo_mora explícito por sobre el fallback por tipo', function () {
            $producto = ProductoFinanciero::factory()->create([
                'tipo' => 'grupal',
                'tipo_calculo_mora' => 'porcentual_sobre_saldo',
            ]);

            expect($producto->moraStrategy())->toBeInstanceOf(MoraPorcentualSobreSaldoStrategy::class);
        });

        it('Mora::calcularMontoMora() delega en la estrategia resuelta desde producto_financiero.tipo_calculo_mora', function () {
            $producto = ProductoFinanciero::factory()->create([
                'tipo' => 'grupal',
                'tipo_calculo_mora' => 'flat_por_integrante',
            ]);
            $grupo = Grupo::factory()->create(['numero_integrantes' => 4]);
            $clientes = Cliente::factory()->count(4)->create();
            $grupo->clientes()->attach($clientes->pluck('id'), [
                'fecha_ingreso' => now(),
                'rol' => 'miembro',
                'estado_grupo_cliente' => 'activo',
            ]);
            $prestamo = Prestamo::factory()->create([
                'grupo_id' => $grupo->id,
                'producto_id' => $producto->id,
            ]);
            $cuota = CuotasGrupales::factory()->create([
                'prestamo_id' => $prestamo->id,
                'fecha_vencimiento' => now()->subDays(5),
            ]);

            // 4 integrantes x 4 dias de atraso (fecha_vencimiento+1 vs hoy) x 1 = 16
            $monto = Mora::calcularMontoMora($cuota, now(), 'pendiente');

            expect($monto)->toEqual(16);
        });
    });
});
