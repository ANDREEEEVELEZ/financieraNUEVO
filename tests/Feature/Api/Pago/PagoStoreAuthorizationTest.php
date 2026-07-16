<?php

use App\Models\Asesor;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\User;
use Spatie\Permission\Models\Permission;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeAsesorForStore(): array
{
    $user   = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'create_pago', 'guard_name' => 'web']));
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    $token  = $user->createToken('test')->plainTextToken;

    return [$user, $asesor, $token];
}

function makeCuotaForAsesor(Asesor $asesor): CuotasGrupales
{
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_ACTIVO,
    ]);

    return CuotasGrupales::factory()->create([
        'prestamo_id'        => $prestamo->id,
        'monto_cuota_grupal' => 150,
    ]);
}

function pagoPayload(CuotasGrupales $cuota): array
{
    return [
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado'    => 50,
        'tipo_pago'       => 'pago_parcial',
    ];
}

// ─── Scenario 1: Asesor creates pago for own cuota → 201 ────────────────────

it('Asesor puede crear un pago para una cuota de su propio grupo', function () {
    [$user, $asesor, $token] = makeAsesorForStore();
    $cuota = makeCuotaForAsesor($asesor);

    $response = $this->withToken($token)->postJson('/api/pagos', pagoPayload($cuota));

    $response->assertCreated()->assertJson(['success' => true]);
    expect(Pago::where('cuota_grupal_id', $cuota->id)->count())->toBe(1);
});

// ─── Scenario 2: Asesor tries to create pago for another asesor's cuota → 403, no row written (IDOR) ──

it('Asesor no puede crear un pago para una cuota fuera de su alcance (IDOR)', function () {
    [$userA, $asesorA, $tokenA] = makeAsesorForStore();
    [$userB, $asesorB, $tokenB] = makeAsesorForStore();

    $cuotaDeB = makeCuotaForAsesor($asesorB);

    $response = $this->withToken($tokenA)->postJson('/api/pagos', pagoPayload($cuotaDeB));

    $response->assertStatus(403)->assertJson(['success' => false, 'message' => 'No autorizado.']);
    expect(Pago::where('cuota_grupal_id', $cuotaDeB->id)->count())->toBe(0);
});

// ─── Scenario 3: Jefe de creditos bypasses asesor scoping → 201 ─────────────

it('Jefe de creditos puede crear un pago para cualquier cuota sin restriccion de asesor', function () {
    [$userA, $asesorA] = makeAsesorForStore();
    $cuotaDeA = makeCuotaForAsesor($asesorA);

    $jc = User::factory()->create(['active' => true]);
    $jc->assignRole('Jefe de creditos');
    $jc->givePermissionTo(Permission::firstOrCreate(['name' => 'create_pago', 'guard_name' => 'web']));
    $jcToken = $jc->createToken('test')->plainTextToken;

    $response = $this->withToken($jcToken)->postJson('/api/pagos', pagoPayload($cuotaDeA));

    $response->assertCreated()->assertJson(['success' => true]);
    expect(Pago::where('cuota_grupal_id', $cuotaDeA->id)->count())->toBe(1);
});

// ─── Scenario 4: Unauthenticated request → 401 ──────────────────────────────

it('rechaza la creacion de pago sin autenticacion', function () {
    [$userA, $asesorA] = makeAsesorForStore();
    $cuota = makeCuotaForAsesor($asesorA);

    $response = $this->postJson('/api/pagos', pagoPayload($cuota));

    $response->assertStatus(401);
});

// ─── Scenario 5: Authenticated user without pagos.crear/create_pago → 403 ───

it('rechaza la creacion de pago para un rol sin permiso de creacion', function () {
    [$userA, $asesorA] = makeAsesorForStore();
    $cuota = makeCuotaForAsesor($asesorA);

    $jo = User::factory()->create(['active' => true]);
    $jo->assignRole('Jefe de operaciones');
    $joToken = $jo->createToken('test')->plainTextToken;

    $response = $this->withToken($joToken)->postJson('/api/pagos', pagoPayload($cuota));

    $response->assertStatus(403);
    expect(Pago::where('cuota_grupal_id', $cuota->id)->count())->toBe(0);
});
