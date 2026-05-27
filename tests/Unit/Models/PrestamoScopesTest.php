<?php

declare(strict_types=1);

use App\Models\Asesor;
use App\Models\Grupo;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('scopeOfAsesor includes prestamo linked via grupo', function () {
    $asesorA = Asesor::factory()->create();
    $asesorB = Asesor::factory()->create();
    $grupoA  = Grupo::factory()->create(['asesor_id' => $asesorA->id]);
    $grupoB  = Grupo::factory()->create(['asesor_id' => $asesorB->id]);

    $prestamoA = Prestamo::factory()->create(['grupo_id' => $grupoA->id]);
    $prestamoB = Prestamo::factory()->create(['grupo_id' => $grupoB->id]);

    $result = Prestamo::ofAsesor($asesorA)->pluck('id');

    expect($result)->toContain($prestamoA->id)->not->toContain($prestamoB->id);
});

it('scopeOfAsesor excludes prestamo from another asesor', function () {
    $asesorA = Asesor::factory()->create();
    $asesorB = Asesor::factory()->create();
    $grupoA  = Grupo::factory()->create(['asesor_id' => $asesorA->id]);
    $grupoB  = Grupo::factory()->create(['asesor_id' => $asesorB->id]);

    Prestamo::factory()->create(['grupo_id' => $grupoA->id]);
    $prestamoB = Prestamo::factory()->create(['grupo_id' => $grupoB->id]);

    $result = Prestamo::ofAsesor($asesorA)->pluck('id');

    expect($result)->not->toContain($prestamoB->id);
});

it('scopeActivo excludes cancelled prestamos', function () {
    $grupo    = Grupo::factory()->create();
    $prestamo = Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'Cancelado']);

    $result = Prestamo::activo()->pluck('id');

    expect($result)->not->toContain($prestamo->id);
});

it('scopeActivo includes active prestamos', function () {
    $grupo    = Grupo::factory()->create();
    $prestamo = Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'Activo']);

    $result = Prestamo::activo()->pluck('id');

    expect($result)->toContain($prestamo->id);
});

it('scopeEnMora returns only En_Mora prestamos', function () {
    $grupo  = Grupo::factory()->create();
    $enMora = Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'En_Mora']);
    $alDia  = Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'Activo']);

    $result = Prestamo::enMora()->pluck('id');

    expect($result)->toContain($enMora->id)->not->toContain($alDia->id);
});
