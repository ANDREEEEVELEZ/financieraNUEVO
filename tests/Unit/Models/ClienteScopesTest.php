<?php

declare(strict_types=1);

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\Grupo;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

// Helper: attach a cliente to a grupo via the pivot table.
function attachToGroup(Cliente $cliente, Grupo $grupo): void
{
    $grupo->clientes()->attach($cliente->id, [
        'fecha_ingreso'         => now()->toDateString(),
        'rol'                   => 'miembro',
        'estado_grupo_cliente'  => 'activo',
    ]);
}

it('scopeOfAsesor filters by asesor_id', function () {
    $asesorA = Asesor::factory()->create();
    $asesorB = Asesor::factory()->create();
    $mine    = Cliente::factory()->create(['asesor_id' => $asesorA->id]);
    $theirs  = Cliente::factory()->create(['asesor_id' => $asesorB->id]);

    $result = Cliente::ofAsesor($asesorA)->pluck('id');

    expect($result)->toContain($mine->id)->not->toContain($theirs->id);
});

it('scopeActivo excludes clientes whose group has only cancelled prestamos', function () {
    $asesor  = Asesor::factory()->create();
    $cliente = Cliente::factory()->create(['asesor_id' => $asesor->id]);
    $grupo   = Grupo::factory()->create(['asesor_id' => $asesor->id]);

    attachToGroup($cliente, $grupo);

    Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'Cancelado']);

    $result = Cliente::activo()->pluck('id');

    expect($result)->not->toContain($cliente->id);
});

it('scopeActivo includes clientes whose group has an active prestamo', function () {
    $asesor  = Asesor::factory()->create();
    $cliente = Cliente::factory()->create(['asesor_id' => $asesor->id]);
    $grupo   = Grupo::factory()->create(['asesor_id' => $asesor->id]);

    attachToGroup($cliente, $grupo);

    Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'Activo']);

    $result = Cliente::activo()->pluck('id');

    expect($result)->toContain($cliente->id);
});

it('scopeConMoraActiva includes clientes with a vencida cuota_individual via their group prestamo', function () {
    $asesor   = Asesor::factory()->create();
    $cliente  = Cliente::factory()->create(['asesor_id' => $asesor->id]);
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);

    attachToGroup($cliente, $grupo);

    $prestamo = Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'En_Mora']);

    CuotaIndividual::factory()->create([
        'prestamo_id' => $prestamo->id,
        'cliente_id'  => $cliente->id,
        'estado'      => 'vencida',
    ]);

    $result = Cliente::conMoraActiva()->pluck('id');

    expect($result)->toContain($cliente->id);
});

it('scopeConMoraActiva excludes clientes with no vencida cuotas', function () {
    $asesor  = Asesor::factory()->create();
    $cliente = Cliente::factory()->create(['asesor_id' => $asesor->id]);
    $grupo   = Grupo::factory()->create(['asesor_id' => $asesor->id]);

    attachToGroup($cliente, $grupo);

    $prestamo = Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'Activo']);

    CuotaIndividual::factory()->create([
        'prestamo_id' => $prestamo->id,
        'cliente_id'  => $cliente->id,
        'estado'      => 'pendiente',
    ]);

    $result = Cliente::conMoraActiva()->pluck('id');

    expect($result)->not->toContain($cliente->id);
});
