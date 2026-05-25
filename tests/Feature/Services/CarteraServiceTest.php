<?php

declare(strict_types=1);

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\User;
use App\Services\CarteraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('returns correct shape with all five keys', function () {
    $user    = User::factory()->create();
    Asesor::factory()->create(['user_id' => $user->id]);

    $result = app(CarteraService::class)->resumen($user);

    expect($result)->toHaveKeys(['grupos_count', 'clientes_count', 'prestamos_count', 'cartera_total', 'cuotas_hoy']);
});

it('cuotas_hoy is always live and not served from cache', function () {
    $user    = User::factory()->create();
    $asesor  = Asesor::factory()->create(['user_id' => $user->id]);
    $cliente = Cliente::factory()->create(['asesor_id' => $asesor->id]);
    $grupo   = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id'   => $grupo->id,
        'cliente_id' => $cliente->id,
        'estado'     => 'Activo',
    ]);

    $service = app(CarteraService::class);

    // First call — warms numeric cache
    $service->resumen($user);

    // Add a new cuota due today AFTER the first call
    $cuota = CuotaIndividual::factory()->create([
        'prestamo_id'      => $prestamo->id,
        'cliente_id'       => $cliente->id,
        'estado'           => 'pendiente',
        'fecha_vencimiento' => today()->toDateString(),
    ]);

    $result = $service->resumen($user);

    $ids = $result['cuotas_hoy']->pluck('id');
    expect($ids)->toContain($cuota->id);
});

it('numeric fields are served from cache on second call', function () {
    $user   = User::factory()->create();
    Asesor::factory()->create(['user_id' => $user->id]);

    $service = app(CarteraService::class);
    $service->resumen($user);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $service->resumen($user);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Only cuotas_hoy query should run; numeric fields from cache
    $numericQueryCount = collect($queries)->filter(fn ($q) =>
        !str_contains($q['query'], 'cuota_individual')
    )->count();

    expect($numericQueryCount)->toBe(0);
});
