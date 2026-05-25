<?php

declare(strict_types=1);

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\Grupo;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('scopeOfAsesor filters by asesor_id', function () {
    $asesorA = Asesor::factory()->create();
    $asesorB = Asesor::factory()->create();
    $mine    = Cliente::factory()->create(['asesor_id' => $asesorA->id]);
    $theirs  = Cliente::factory()->create(['asesor_id' => $asesorB->id]);

    $result = Cliente::ofAsesor($asesorA)->pluck('id');

    expect($result)->toContain($mine->id)->not->toContain($theirs->id);
});

it('scopeActivo excludes clientes with only cancelled prestamos', function () {
    $asesor   = Asesor::factory()->create();
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $cliente  = Cliente::factory()->create(['asesor_id' => $asesor->id]);

    Prestamo::factory()->create([
        'grupo_id'   => $grupo->id,
        'cliente_id' => $cliente->id,
        'estado'     => 'Cancelado',
    ]);

    $result = Cliente::activo()->pluck('id');

    expect($result)->not->toContain($cliente->id);
});

it('scopeActivo includes clientes with an active prestamo', function () {
    $asesor   = Asesor::factory()->create();
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $cliente  = Cliente::factory()->create(['asesor_id' => $asesor->id]);

    Prestamo::factory()->create([
        'grupo_id'   => $grupo->id,
        'cliente_id' => $cliente->id,
        'estado'     => 'Activo',
    ]);

    $result = Cliente::activo()->pluck('id');

    expect($result)->toContain($cliente->id);
});

it('scopeConMoraActiva includes clientes with a vencida cuota_individual', function () {
    $asesor   = Asesor::factory()->create();
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $cliente  = Cliente::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id'   => $grupo->id,
        'cliente_id' => $cliente->id,
        'estado'     => 'En_Mora',
    ]);

    CuotaIndividual::factory()->create([
        'prestamo_id' => $prestamo->id,
        'cliente_id'  => $cliente->id,
        'estado'      => 'vencida',
    ]);

    $result = Cliente::conMoraActiva()->pluck('id');

    expect($result)->toContain($cliente->id);
});

it('scopeConMoraActiva excludes clientes with no vencida cuotas', function () {
    $asesor   = Asesor::factory()->create();
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $cliente  = Cliente::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id'   => $grupo->id,
        'cliente_id' => $cliente->id,
        'estado'     => 'Activo',
    ]);

    CuotaIndividual::factory()->create([
        'prestamo_id' => $prestamo->id,
        'cliente_id'  => $cliente->id,
        'estado'      => 'pendiente',
    ]);

    $result = Cliente::conMoraActiva()->pluck('id');

    expect($result)->not->toContain($cliente->id);
});
