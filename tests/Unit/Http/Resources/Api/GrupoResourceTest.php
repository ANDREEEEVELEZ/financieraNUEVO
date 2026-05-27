<?php

use App\Http\Resources\Api\GrupoResource;
use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\Grupo;
use App\Models\Persona;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Http\Request;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Resolve GrupoResource fully (nested resources serialized).
 */
function grupoResourceData(Grupo $grupo, ?User $viewer = null): array
{
    $viewer ??= User::factory()->create(['active' => true]);
    $viewer->assignRole('Asesor');

    auth()->login($viewer);

    $request = Request::create('/api/grupos/1', 'GET');
    $request->setUserResolver(fn () => $viewer);

    $resource = new GrupoResource($grupo);

    return $resource->response($request)->getData(true)['data'];
}

it('includes calificacion_grupo in always-present block', function () {
    $grupo = Grupo::factory()->create(['calificacion_grupo' => 'A']);

    $data = grupoResourceData($grupo);

    expect($data)->toHaveKey('calificacion_grupo');
});

it('includes fecha_registro in Y-m-d format in always-present block', function () {
    $grupo = Grupo::factory()->create(['fecha_registro' => '2024-01-15']);

    $data = grupoResourceData($grupo);

    expect($data)->toHaveKey('fecha_registro');
    // Value must be a date string (Y-m-d) or null
    if ($data['fecha_registro'] !== null) {
        expect($data['fecha_registro'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    }
});

it('includes asesor object with id and nombre_completo when asesor is loaded', function () {
    $asesor = Asesor::factory()->create();
    $grupo  = Grupo::factory()->create(['asesor_id' => $asesor->id]);

    // Eager-load asesor with persona
    $grupoLoaded = Grupo::with('asesor.persona')->find($grupo->id);

    $data = grupoResourceData($grupoLoaded);

    expect($data)->toHaveKey('asesor');
    expect($data['asesor'])->toBeArray();
    expect($data['asesor'])->toHaveKey('id');
    expect($data['asesor'])->toHaveKey('nombre_completo');
});

it('exposes raw asesor_id when asesor relation is NOT loaded', function () {
    $asesor = Asesor::factory()->create();
    $grupo  = Grupo::factory()->create(['asesor_id' => $asesor->id]);

    // Fresh load — no eager-loading of asesor
    $grupoFresh = Grupo::find($grupo->id);

    $data = grupoResourceData($grupoFresh);

    expect($data)->toHaveKey('asesor_id');
    expect($data['asesor_id'])->toBe($asesor->id);
});

it('omits raw asesor_id when asesor relation IS loaded', function () {
    $asesor = Asesor::factory()->create();
    $grupo  = Grupo::factory()->create(['asesor_id' => $asesor->id]);

    $grupoLoaded = Grupo::with('asesor.persona')->find($grupo->id);

    $data = grupoResourceData($grupoLoaded);

    expect($data)->not->toHaveKey('asesor_id');
});

it('includes integrantes with rol when clientes relation is loaded', function () {
    $asesor  = Asesor::factory()->create();
    $grupo   = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $cliente = Cliente::factory()->create();

    // Attach with pivot data
    $grupo->clientes()->attach($cliente->id, [
        'rol'                  => 'Líder Grupal',
        'fecha_ingreso'        => '2023-06-01',
        'estado_grupo_cliente' => 'Activo',
    ]);

    $grupoLoaded = Grupo::with('clientes.persona')->find($grupo->id);

    $data = grupoResourceData($grupoLoaded);

    expect($data)->toHaveKey('integrantes');
    expect($data['integrantes'])->toBeArray();
    expect($data['integrantes'][0])->toHaveKey('rol');
    expect($data['integrantes'][0]['rol'])->toBe('Líder Grupal');
});

it('includes prestamo_activo with frecuencia and tasa_interes when prestamos relation is loaded', function () {
    $asesor  = Asesor::factory()->create();
    $grupo   = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    Prestamo::factory()->create([
        'grupo_id'  => $grupo->id,
        'estado'    => 'Activo',
        'frecuencia' => 'Semanal',
    ]);

    $grupoLoaded = Grupo::with(['prestamos' => fn ($q) => $q->where('estado', 'Activo')])->find($grupo->id);

    $data = grupoResourceData($grupoLoaded);

    expect($data)->toHaveKey('prestamo_activo');
    expect($data['prestamo_activo'])->toBeArray();
    expect($data['prestamo_activo'])->toHaveKey('frecuencia');
    expect($data['prestamo_activo'])->toHaveKey('tasa_interes');
    expect($data['prestamo_activo'])->toHaveKey('monto_devolver');
});
