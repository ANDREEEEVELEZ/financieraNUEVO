<?php

declare(strict_types=1);

use App\Jobs\ActualizarMetricasDiariasJob;
use App\Models\AplicacionPago;
use App\Models\Asesor;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\MetricaDiariaAsesor;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('creates a metricas_diarias_asesor row for each asesor user', function () {
    $user   = User::factory()->create();
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);

    (new ActualizarMetricasDiariasJob())->handle();

    expect(MetricaDiariaAsesor::where('asesor_id', $user->id)->count())->toBe(1);
});

it('is idempotent — re-running produces exactly one row per asesor per day', function () {
    $user   = User::factory()->create();
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);

    (new ActualizarMetricasDiariasJob())->handle();
    (new ActualizarMetricasDiariasJob())->handle();

    expect(MetricaDiariaAsesor::where('asesor_id', $user->id)->where('fecha', today())->count())->toBe(1);
});

it('forgets meta_cobranza and cartera_resumen cache keys after write', function () {
    $user   = User::factory()->create();
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);

    $cacheKey1 = "meta_cobranza:{$user->id}:" . now()->format('Y-m');
    $cacheKey2 = "cartera_resumen:{$user->id}";

    Cache::put($cacheKey1, ['meta' => 1.0, 'cobrado' => 1.0, 'pct' => 1.0], 300);
    Cache::put($cacheKey2, ['grupos_count' => 1], 300);

    (new ActualizarMetricasDiariasJob())->handle();

    expect(Cache::has($cacheKey1))->toBeFalse();
    expect(Cache::has($cacheKey2))->toBeFalse();
});

/**
 * Task 3.13 — confirmed 11th cobranza-metrics reader (SDD core-contable-seguridad,
 * Req 2 item 10 / spec risk #2). This job sums aplicacion_pago joined through
 * pagos/cuotas_grupales/prestamos/grupos with no origin filter; it MUST exclude
 * tipo_aplicacion 'retanqueo' rows from MetricaDiariaAsesor.cobrado (Scenario 2.1).
 */
it('excludes retanqueo-tipo aplicaciones from MetricaDiariaAsesor.cobrado', function () {
    $user   = User::factory()->create();
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    $grupo  = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_ACTIVO,
    ]);
    $cuotaGrupal = CuotasGrupales::factory()->create(['prestamo_id' => $prestamo->id]);
    $cuotaIndividual = CuotaIndividual::factory()->create(['prestamo_id' => $prestamo->id]);

    $pagoCobranza = Pago::factory()->create([
        'cuota_grupal_id' => $cuotaGrupal->id,
        'origen_pago'     => 'cobranza',
        'estado_pago'     => 'aprobado',
        'fecha_pago'      => today(),
    ]);
    AplicacionPago::create([
        'pago_id'                => $pagoCobranza->id,
        'cuota_id'               => $cuotaIndividual->id,
        'tipo_aplicacion'        => 'cobranza',
        'monto_aplicado_capital' => 60.00,
        'monto_aplicado_interes' => 20.00,
        'monto_aplicado_mora'    => 0.00,
        'fecha_aplicacion'       => today(),
    ]);

    $pagoRetanqueo = Pago::factory()->create([
        'cuota_grupal_id' => $cuotaGrupal->id,
        'origen_pago'     => 'retanqueo',
        'estado_pago'     => 'aprobado',
        'fecha_pago'      => today(),
    ]);
    AplicacionPago::create([
        'pago_id'                => $pagoRetanqueo->id,
        'cuota_id'               => $cuotaIndividual->id,
        'tipo_aplicacion'        => 'retanqueo',
        'monto_aplicado_capital' => 40.00,
        'monto_aplicado_interes' => 0.00,
        'monto_aplicado_mora'    => 0.00,
        'fecha_aplicacion'       => today(),
    ]);

    (new ActualizarMetricasDiariasJob())->handle();

    $metrica = MetricaDiariaAsesor::where('asesor_id', $user->id)->where('fecha', today())->first();

    // Only the cobranza-tipo application (60 + 20 = 80.00) counts as "cobrado";
    // the retanqueo-tipo application (40.00) MUST be excluded.
    expect((float) $metrica->cobrado)->toEqual(80.0);
});
