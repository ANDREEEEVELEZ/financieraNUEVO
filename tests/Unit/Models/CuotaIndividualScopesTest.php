<?php

declare(strict_types=1);

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\Grupo;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('scopeDueToday includes pendiente cuota due today', function () {
    $grupo    = Grupo::factory()->create();
    $prestamo = Prestamo::factory()->create(['grupo_id' => $grupo->id]);
    $cliente  = Cliente::factory()->create();

    $cuota = CuotaIndividual::factory()->create([
        'prestamo_id'       => $prestamo->id,
        'cliente_id'        => $cliente->id,
        'estado'            => 'pendiente',
        'fecha_vencimiento' => today()->toDateString(),
    ]);

    $result = CuotaIndividual::dueToday()->pluck('id');

    expect($result)->toContain($cuota->id);
});

it('scopeDueToday excludes vencida cuota from yesterday', function () {
    $grupo    = Grupo::factory()->create();
    $prestamo = Prestamo::factory()->create(['grupo_id' => $grupo->id]);
    $cliente  = Cliente::factory()->create();

    $cuota = CuotaIndividual::factory()->create([
        'prestamo_id'       => $prestamo->id,
        'cliente_id'        => $cliente->id,
        'estado'            => 'vencida',
        'fecha_vencimiento' => today()->subDay()->toDateString(),
    ]);

    $result = CuotaIndividual::dueToday()->pluck('id');

    expect($result)->not->toContain($cuota->id);
});

it('scopeEnMora returns cuotas with estado vencida', function () {
    $grupo    = Grupo::factory()->create();
    $prestamo = Prestamo::factory()->create(['grupo_id' => $grupo->id]);
    $cliente  = Cliente::factory()->create();

    $vencida   = CuotaIndividual::factory()->create([
        'prestamo_id' => $prestamo->id,
        'cliente_id'  => $cliente->id,
        'estado'      => 'vencida',
    ]);
    $pendiente = CuotaIndividual::factory()->create([
        'prestamo_id' => $prestamo->id,
        'cliente_id'  => $cliente->id,
        'estado'      => 'pendiente',
    ]);

    $result = CuotaIndividual::enMora()->pluck('id');

    expect($result)->toContain($vencida->id)->not->toContain($pendiente->id);
});

it('scopeOfAsesor excludes cuotas from another asesor', function () {
    $asesorA = Asesor::factory()->create();
    $asesorB = Asesor::factory()->create();

    $grupoA = Grupo::factory()->create(['asesor_id' => $asesorA->id]);
    $grupoB = Grupo::factory()->create(['asesor_id' => $asesorB->id]);

    $prestamoA = Prestamo::factory()->create(['grupo_id' => $grupoA->id]);
    $prestamoB = Prestamo::factory()->create(['grupo_id' => $grupoB->id]);

    $clienteA = Cliente::factory()->create(['asesor_id' => $asesorA->id]);
    $clienteB = Cliente::factory()->create(['asesor_id' => $asesorB->id]);

    $cuotaA = CuotaIndividual::factory()->create([
        'prestamo_id' => $prestamoA->id,
        'cliente_id'  => $clienteA->id,
    ]);
    $cuotaB = CuotaIndividual::factory()->create([
        'prestamo_id' => $prestamoB->id,
        'cliente_id'  => $clienteB->id,
    ]);

    $result = CuotaIndividual::ofAsesor($asesorA)->pluck('id');

    expect($result)->toContain($cuotaA->id)->not->toContain($cuotaB->id);
});
