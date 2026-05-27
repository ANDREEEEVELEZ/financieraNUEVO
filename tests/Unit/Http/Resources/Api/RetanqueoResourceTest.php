<?php

use App\Http\Resources\Api\RetanqueoResource;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\Retanqueo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function retanqueoResourceMakeRetanqueo(): Retanqueo
{
    $grupo    = Grupo::factory()->create();
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_ACTIVO,
    ]);

    $id = DB::table('retanqueos')->insertGetId([
        'prestamo_id'                     => $prestamo->id,
        'prestamo_nuevo_id'               => null,
        'monto_retanqueo'                 => 1000.00,
        'monto_usado_para_cubrir_antiguo' => 200.00,
        'monto_desembolsar'               => 800.00,
        'monto_cuota'                     => 100.00,
        'cantidad_cuotas_nuevo'           => 10,
        'saldo_restante_prestamo_antiguo' => 0.00,
        'prestamo_antiguo_estado'         => 0,
        'fecha_aceptacion'                => null,
        'estado_retanqueo'                => 'solicitud_pendiente',
        'created_at'                      => now(),
        'updated_at'                      => now(),
    ]);

    return Retanqueo::findOrFail($id);
}

function retanqueoResourceData(Retanqueo $retanqueo): array
{
    $request = Request::create('/');
    return (new RetanqueoResource($retanqueo))->response($request)->getData(true)['data'];
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('all 9 base fields are present', function () {
    $retanqueo = retanqueoResourceMakeRetanqueo();
    $data      = retanqueoResourceData($retanqueo);

    expect($data)->toHaveKey('id');
    expect($data)->toHaveKey('prestamo_id');
    expect($data)->toHaveKey('prestamo_nuevo_id');
    expect($data)->toHaveKey('monto_retanqueo');
    expect($data)->toHaveKey('monto_desembolsar');
    expect($data)->toHaveKey('monto_cuota');
    expect($data)->toHaveKey('cantidad_cuotas_nuevo');
    expect($data)->toHaveKey('estado_retanqueo');
    expect($data)->toHaveKey('fecha_aceptacion');
});

it('grupo_nombre is absent when prestamoAntiguo is not loaded', function () {
    $retanqueo = retanqueoResourceMakeRetanqueo();
    $data      = retanqueoResourceData($retanqueo);

    expect($data)->not->toHaveKey('grupo_nombre');
});

it('estado_retanqueo is a string not estado', function () {
    $retanqueo = retanqueoResourceMakeRetanqueo();
    $data      = retanqueoResourceData($retanqueo);

    expect($data)->toHaveKey('estado_retanqueo');
    expect($data)->not->toHaveKey('estado');
    expect($data['estado_retanqueo'])->toBeString();
});

it('grupo_nombre is present when prestamoAntiguo is loaded', function () {
    $retanqueo = retanqueoResourceMakeRetanqueo();
    $retanqueo->load('prestamoAntiguo');
    $data = retanqueoResourceData($retanqueo);

    expect($data)->toHaveKey('grupo_nombre');
});
