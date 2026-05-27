<?php

use App\Http\Resources\Api\MoraResource;
use App\Models\Mora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function moraResourceMakeMora(?string $fechaAtraso, string $estado = 'pendiente'): Mora
{
    // Insert via DB to avoid N+1 from model relationships
    $id = DB::table('moras')->insertGetId([
        'cuota_grupal_id' => null,
        'fecha_atraso'    => $fechaAtraso,
        'estado_mora'     => $estado,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    return Mora::findOrFail($id);
}

function moraResourceData(Mora $mora): array
{
    $request = Request::create('/');
    return (new MoraResource($mora))->response($request)->getData(true)['data'];
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('dias_atraso is a positive integer for a past fecha_atraso', function () {
    $mora = moraResourceMakeMora(now()->subDays(5)->toDateString());
    $data = moraResourceData($mora);

    expect($data)->toHaveKey('dias_atraso');
    expect($data['dias_atraso'])->toBeInt();
    expect($data['dias_atraso'])->toBeGreaterThan(0);
});

it('dias_atraso is null when fecha_atraso is null', function () {
    $mora = moraResourceMakeMora(null);
    $data = moraResourceData($mora);

    expect($data)->toHaveKey('dias_atraso');
    expect($data['dias_atraso'])->toBeNull();
});

it('estado_mora is present', function () {
    $mora = moraResourceMakeMora(null);
    $data = moraResourceData($mora);

    expect($data)->toHaveKey('estado_mora');
});

it('id and cuota_grupal_id are present', function () {
    $mora = moraResourceMakeMora(null);
    $data = moraResourceData($mora);

    expect($data)->toHaveKey('id');
    expect($data)->toHaveKey('cuota_grupal_id');
});
