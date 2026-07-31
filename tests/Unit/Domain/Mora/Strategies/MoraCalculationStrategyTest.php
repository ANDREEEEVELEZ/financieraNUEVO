<?php

use App\Domain\Mora\Strategies\MoraCalculationStrategy;
use App\Domain\Mora\Strategies\MoraCalculoInput;
use App\Domain\Mora\Strategies\MoraFlatPorIntegranteStrategy;
use App\Domain\Mora\Strategies\MoraPorcentualSobreSaldoStrategy;

/**
 * Eje 2 (dominio-pagos-mora-retanqueo) — Task 2.7
 * Pure unit tests for the Mora Strategy pattern — no DB, formulas in isolation.
 */
it('MoraCalculationStrategy is an interface', function () {
    $reflection = new ReflectionClass(MoraCalculationStrategy::class);
    expect($reflection->isInterface())->toBeTrue();
});

it('MoraFlatPorIntegranteStrategy implements MoraCalculationStrategy', function () {
    expect(
        is_a(MoraFlatPorIntegranteStrategy::class, MoraCalculationStrategy::class, true)
    )->toBeTrue();
});

it('MoraPorcentualSobreSaldoStrategy implements MoraCalculationStrategy', function () {
    expect(
        is_a(MoraPorcentualSobreSaldoStrategy::class, MoraCalculationStrategy::class, true)
    )->toBeTrue();
});

describe('MoraFlatPorIntegranteStrategy', function () {
    it('calcula integrantes x dias_atraso x 1', function () {
        $strategy = new MoraFlatPorIntegranteStrategy();

        $monto = $strategy->calcular(new MoraCalculoInput(
            diasAtraso: 4,
            numeroIntegrantes: 3,
        ));

        expect($monto)->toEqual(12);
    });

    it('devuelve 0 cuando no hay dias de atraso', function () {
        $strategy = new MoraFlatPorIntegranteStrategy();

        $monto = $strategy->calcular(new MoraCalculoInput(
            diasAtraso: 0,
            numeroIntegrantes: 5,
        ));

        expect($monto)->toEqual(0);
    });

    it('trata numeroIntegrantes null como 0 (defensivo)', function () {
        $strategy = new MoraFlatPorIntegranteStrategy();

        $monto = $strategy->calcular(new MoraCalculoInput(diasAtraso: 10));

        expect($monto)->toEqual(0);
    });
});

describe('MoraPorcentualSobreSaldoStrategy', function () {
    it('calcula saldo_capital x (tasa_mora/30) x dias_atraso', function () {
        $strategy = new MoraPorcentualSobreSaldoStrategy();

        // 1000 * (0.05/30) * 30 dias = 50
        $monto = $strategy->calcular(new MoraCalculoInput(
            diasAtraso: 30,
            saldoCapital: 1000.0,
            tasaMora: 0.05,
        ));

        expect($monto)->toEqual(50.0);
    });

    it('resta las condonaciones del monto base', function () {
        $strategy = new MoraPorcentualSobreSaldoStrategy();

        // base = 50, condonaciones = 20 => 30
        $monto = $strategy->calcular(new MoraCalculoInput(
            diasAtraso: 30,
            saldoCapital: 1000.0,
            tasaMora: 0.05,
            condonaciones: 20.0,
        ));

        expect($monto)->toEqual(30.0);
    });

    it('nunca devuelve un monto negativo (piso en 0) aunque las condonaciones excedan la base', function () {
        $strategy = new MoraPorcentualSobreSaldoStrategy();

        $monto = $strategy->calcular(new MoraCalculoInput(
            diasAtraso: 30,
            saldoCapital: 1000.0,
            tasaMora: 0.05,
            condonaciones: 999.0,
        ));

        expect($monto)->toEqual(0);
    });
});
