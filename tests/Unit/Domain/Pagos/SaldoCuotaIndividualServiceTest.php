<?php

use App\Domain\Pagos\SaldoCuotaIndividualService;
use App\Models\AplicacionPago;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\ProductoFinanciero;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses(Tests\TestCase::class);

/**
 * Task 4.5 — SaldoCuotaIndividualService unit tests.
 * Mirrors tests/Unit/Domain/Pagos/SaldoCuotaServiceTest.php (grupal) in
 * style. Ledger-derived: capital+interés vienen de las columnas
 * saldo_capital/saldo_interes (mutadas por el waterfall único del Eje 3);
 * mora viene de CuotaIndividual::moraCalculada() (Eje 4), ya neta de
 * AplicacionPago.monto_aplicado_mora.
 */
function makeCuotaIndividualVencida(array $overrides = [], ?ProductoFinanciero $producto = null): CuotaIndividual
{
    // tasa_mora alta (30% mensual) para asegurar margen suficiente en los
    // escenarios que descuentan varios pagos parciales de mora sin que el
    // floor-en-0 de moraCalculada() enmascare el cálculo bajo prueba.
    $producto ??= ProductoFinanciero::factory()->create([
        'tipo' => 'individual',
        'tasa_mora' => 0.30,
    ]);

    $prestamo = Prestamo::factory()->create([
        'producto_id' => $producto->id,
        'estado' => Prestamo::ESTADO_ACTIVO,
    ]);

    $cliente = Cliente::factory()->create();

    return CuotaIndividual::factory()->create(array_merge([
        'prestamo_id' => $prestamo->id,
        'cliente_id' => $cliente->id,
        'estado' => 'vencida',
        'fecha_vencimiento' => now()->subDays(10)->toDateString(),
        'saldo_capital' => 100.00,
        'saldo_interes' => 10.00,
    ], $overrides));
}

/**
 * Crea un AplicacionPago de mora para la cuota, respaldado por un Pago
 * aprobado real (aplicacion_pago.pago_id es NOT NULL con FK a pagos).
 */
function registrarAplicacionMora(CuotaIndividual $cuota, float $montoMora): AplicacionPago
{
    $pago = Pago::factory()->create([
        'cuota_grupal_id' => null,
        'estado_pago' => 'aprobado',
    ]);

    return AplicacionPago::create([
        'pago_id' => $pago->id,
        'cuota_id' => $cuota->id,
        'tipo_aplicacion' => 'cobranza',
        'monto_aplicado_capital' => 0,
        'monto_aplicado_interes' => 0,
        'monto_aplicado_mora' => $montoMora,
        'fecha_aplicacion' => now(),
    ]);
}

it('saldoCapitalInteres returns saldo_capital + saldo_interes', function () {
    $cuota = makeCuotaIndividualVencida(['saldo_capital' => 100.00, 'saldo_interes' => 10.00]);

    $service = new SaldoCuotaIndividualService;

    expect($service->saldoCapitalInteres($cuota))->toBe('110.00');
});

it('saldoMora returns moraCalculada formatted as bcmath string', function () {
    // tasa_mora 5% mensual => diaria = 0.05/30; saldoCapital 100; diasAtraso 10.
    $cuota = makeCuotaIndividualVencida(['saldo_capital' => 100.00]);

    $service = new SaldoCuotaIndividualService;

    $esperado = number_format($cuota->moraCalculada(), 2, '.', '');
    expect($service->saldoMora($cuota))->toBe($esperado);
    expect((float) $esperado)->toBeGreaterThan(0);
});

it('saldoMora returns 0.00 when cuota is not overdue', function () {
    $producto = ProductoFinanciero::factory()->create(['tipo' => 'individual']);
    $prestamo = Prestamo::factory()->create(['producto_id' => $producto->id]);
    $cliente = Cliente::factory()->create();

    $cuota = CuotaIndividual::factory()->create([
        'prestamo_id' => $prestamo->id,
        'cliente_id' => $cliente->id,
        'estado' => 'pendiente',
        'fecha_vencimiento' => now()->addDays(5)->toDateString(),
    ]);

    $service = new SaldoCuotaIndividualService;

    expect($service->saldoMora($cuota))->toBe('0.00');
});

it('saldoTotal equals saldoCapitalInteres plus saldoMora', function () {
    $cuota = makeCuotaIndividualVencida(['saldo_capital' => 100.00, 'saldo_interes' => 10.00]);

    $service = new SaldoCuotaIndividualService;

    $expectedTotal = bcadd($service->saldoCapitalInteres($cuota), $service->saldoMora($cuota), 2);
    expect($service->saldoTotal($cuota))->toBe($expectedTotal);
});

it('saldoTotal floors mora at 0 without letting a mora overpayment reduce capital+interés — buckets do not cross-subsidize', function () {
    // MoraPorcentualSobreSaldoStrategy ata la mora al saldo_capital vigente,
    // así que el escenario "capital en 0 pero mora > 0" no es alcanzable con
    // esta fórmula (out of scope de este Eje). La dirección SÍ observable de
    // "no cross-subsidización" es la inversa: un sobrepago de mora no debe
    // filtrarse y reducir el saldo de capital+interés — son buckets
    // computados de forma completamente independiente.
    $cuota = makeCuotaIndividualVencida(['saldo_capital' => 100.00, 'saldo_interes' => 10.00]);

    $moraGenerada = $cuota->moraCalculada();
    expect($moraGenerada)->toBeGreaterThan(0);

    registrarAplicacionMora($cuota, $moraGenerada + 50.00);
    $cuota = $cuota->fresh();

    $service = new SaldoCuotaIndividualService;

    expect($service->saldoMora($cuota))->toBe('0.00');
    expect($service->saldoCapitalInteres($cuota))->toBe('110.00');
    expect($service->saldoTotal($cuota))->toBe('110.00');
});

// ─────────────────────────────────────────────────────────────────────────
// Problem 1 — mora ya pagada se descuenta (ledger-derived vía AplicacionPago)
// ─────────────────────────────────────────────────────────────────────────

it('moraCalculada nets out mora already paid via AplicacionPago', function () {
    $cuota = makeCuotaIndividualVencida(['saldo_capital' => 100.00]);

    $moraGeneradaBruta = $cuota->moraCalculada();
    expect($moraGeneradaBruta)->toBeGreaterThan(0);

    registrarAplicacionMora($cuota, 2.00);

    $moraNeta = $cuota->fresh()->moraCalculada();

    expect(bccomp(
        number_format($moraNeta, 2, '.', ''),
        bcsub(number_format($moraGeneradaBruta, 2, '.', ''), '2.00', 2),
        2
    ))->toBe(0);
});

it('moraCalculada floors at 0 when mora paid exceeds mora generada', function () {
    $cuota = makeCuotaIndividualVencida(['saldo_capital' => 100.00]);

    $moraGeneradaBruta = $cuota->moraCalculada();

    // Sobrepagar la mora respecto de lo generado.
    registrarAplicacionMora($cuota, $moraGeneradaBruta + 50.00);

    expect($cuota->fresh()->moraCalculada())->toBe(0.0);
});

/**
 * Escenario crítico (Task 4.5 / design.md Eje 4, Eje 6 verificación manual):
 * un cliente hace DOS pagos parciales de mora en secuencia sobre la misma
 * cuota vencida. El segundo cálculo de moraCalculada() NO debe volver a
 * cobrar los días de atraso ya cubiertos por el primer pago — antes del fix
 * (Problema 1), moraCalculada() recalculaba desde cero sin descontar lo
 * pagado, así que este test habría fallado (segunda mora == mora bruta).
 */
it('pagar mora parcialmente dos veces no re-cobra los mismos días de atraso', function () {
    $cuota = makeCuotaIndividualVencida(['saldo_capital' => 100.00]);

    $moraGeneradaBruta = $cuota->moraCalculada();
    expect($moraGeneradaBruta)->toBeGreaterThan(4.00);

    // Primer pago parcial de mora.
    registrarAplicacionMora($cuota, 2.00);
    $moraDespuesPago1 = $cuota->fresh()->moraCalculada();

    expect(bccomp(
        number_format($moraDespuesPago1, 2, '.', ''),
        bcsub(number_format($moraGeneradaBruta, 2, '.', ''), '2.00', 2),
        2
    ))->toBe(0);

    // Segundo pago parcial de mora (misma cuota, mismos días de atraso —
    // no pasó el tiempo entre ambos pagos en este test).
    registrarAplicacionMora($cuota, 1.50);
    $moraDespuesPago2 = $cuota->fresh()->moraCalculada();

    $esperadaDespuesPago2 = bcsub(number_format($moraGeneradaBruta, 2, '.', ''), '3.50', 2);

    expect(bccomp(number_format($moraDespuesPago2, 2, '.', ''), $esperadaDespuesPago2, 2))->toBe(0);

    // La regresión que este test previene: si moraCalculada() no descontara
    // lo ya pagado, moraDespuesPago2 sería igual a moraGeneradaBruta otra
    // vez (re-cobro de los mismos días).
    expect(number_format($moraDespuesPago2, 2, '.', ''))
        ->not->toBe(number_format($moraGeneradaBruta, 2, '.', ''));

    $service = new SaldoCuotaIndividualService;
    expect($service->saldoMora($cuota->fresh()))->toBe($esperadaDespuesPago2);
});
