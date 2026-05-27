<?php

use App\Models\Asesor;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\User;
use App\Domain\Pagos\PagoService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function pagoTestMakeAsesor(): array
{
    $user   = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    $token  = $user->createToken('test')->plainTextToken;

    return [$user, $asesor, $token];
}

function pagoTestMakeJo(): array
{
    $user  = User::factory()->create(['active' => true]);
    $user->assignRole('Jefe de operaciones');
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $token];
}

function pagoTestMakeSa(): array
{
    $user  = User::factory()->create(['active' => true]);
    $user->assignRole('super_admin');
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $token];
}

/**
 * Create a cuota_grupal backed by a prestamo in estado Activo.
 * Only Activo/Al Día/En Mora prestamos pass Pago::boot() guard.
 */
function pagoTestMakeCuotaActiva(Grupo $grupo): CuotasGrupales
{
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_ACTIVO,
        'fecha_desembolso' => now()->toDateString(),
    ]);

    return CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'estado_cuota_grupal' => 'vigente',
        'estado_pago' => 'pendiente',
    ]);
}

// ─── Scenario 1: Asesor creates pago for own cuota → 201 ────────────────────

it('Asesor creates pago for own cuota and gets 201', function () {
    [$user, $asesor, $token] = pagoTestMakeAsesor();
    $grupo  = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $cuota  = pagoTestMakeCuotaActiva($grupo);

    $payload = [
        'cuota_grupal_id'  => $cuota->id,
        'monto_pagado'     => 150.00,
        'tipo_pago'        => 'efectivo',
        'codigo_operacion' => null,
    ];

    $response = $this->withToken($token)->postJson('/api/pagos', $payload);

    $response->assertCreated()
             ->assertJson(['success' => true])
             ->assertJsonPath('data.estado_pago', 'pendiente')
             ->assertJsonPath('data.cuota_grupal_id', $cuota->id);
});

// ─── Scenario 2: Pago::boot() exception (invalid estado) → 422 ──────────────
// When the prestamo is not in an active state, boot() throws \Exception.

it('Creating pago for non-active prestamo returns 422 with domain exception message', function () {
    [$user, $asesor, $token] = pagoTestMakeAsesor();
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_PENDIENTE,
    ]);
    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
    ]);

    $payload = [
        'cuota_grupal_id' => $cuota->id,
        'monto_pagado'    => 100.00,
        'tipo_pago'       => 'efectivo',
    ];

    $response = $this->withToken($token)->postJson('/api/pagos', $payload);

    $response->assertStatus(422)
             ->assertJson(['success' => false]);

    expect($response->json('message'))->not->toBeEmpty();
});

/**
 * Create a Pago record bypassing boot() by inserting directly via DB.
 * boot() fires on Eloquent create(). For tests that only need a Pago row
 * to test authorization, we bypass the boot guard using DB::table().
 */
function pagoTestInsertPago(int $cuotaGrupalId, string $estado): Pago
{
    $id = \Illuminate\Support\Facades\DB::table('pagos')->insertGetId([
        'cuota_grupal_id'  => $cuotaGrupalId,
        'tipo_pago'        => 'efectivo',
        'codigo_operacion' => 'TEST-001',
        'monto_pagado'     => 150.00,
        'monto_mora_pagada'=> 0,
        'fecha_pago'       => now()->toDateTimeString(),
        'estado_pago'      => $estado,
        'observaciones'    => null,
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
    return Pago::findOrFail($id);
}

// ─── Scenario 3: JO approves pago → 200 ─────────────────────────────────────
// PagoService::aprobarPago is mocked to avoid full workflow setup.
// NOTE: Pago is inserted via DB to bypass boot() guard on factory create.

it('JO approves pago and gets 200', function () {
    [$joUser, $joToken] = pagoTestMakeJo();
    $cuota = pagoTestMakeCuotaActiva(Grupo::factory()->create());
    $pago  = pagoTestInsertPago($cuota->id, 'pendiente');

    $this->mock(PagoService::class, function ($mock) use ($pago) {
        $mock->shouldReceive('aprobarPago')
             ->once()
             ->with(\Mockery::on(fn ($p) => $p->id === $pago->id))
             ->andReturn($pago);
    });

    $response = $this->withToken($joToken)
                     ->patchJson("/api/pagos/{$pago->id}/aprobar");

    $response->assertOk()
             ->assertJson(['success' => true]);
});

// ─── Scenario 4: Asesor tries to approve pago → 403 ─────────────────────────

it('Asesor trying to approve pago gets 403', function () {
    [$user, $asesor, $token] = pagoTestMakeAsesor();
    $cuota = pagoTestMakeCuotaActiva(Grupo::factory()->create(['asesor_id' => $asesor->id]));
    $pago  = pagoTestInsertPago($cuota->id, 'pendiente');

    $response = $this->withToken($token)
                     ->patchJson("/api/pagos/{$pago->id}/aprobar");

    $response->assertForbidden()
             ->assertJson(['success' => false]);
});

// ─── Scenario 5: JO reverts pago → 200 ──────────────────────────────────────
// PagoService::revertirPago is mocked.

it('JO reverts pago and gets 200', function () {
    [$joUser, $joToken] = pagoTestMakeJo();
    $cuota = pagoTestMakeCuotaActiva(Grupo::factory()->create());
    $pago  = pagoTestInsertPago($cuota->id, 'aprobado');

    $this->mock(PagoService::class, function ($mock) use ($pago) {
        $mock->shouldReceive('revertirPago')
             ->once()
             ->with(\Mockery::on(fn ($p) => $p->id === $pago->id))
             ->andReturn(true);
    });

    $response = $this->withToken($joToken)
                     ->patchJson("/api/pagos/{$pago->id}/revertir");

    $response->assertOk()
             ->assertJson(['success' => true]);
});

// ─── Scenario 6: SA reverts pago → 200 ──────────────────────────────────────

it('SA reverts pago and gets 200', function () {
    [$saUser, $saToken] = pagoTestMakeSa();
    $cuota = pagoTestMakeCuotaActiva(Grupo::factory()->create());
    $pago  = pagoTestInsertPago($cuota->id, 'aprobado');

    $this->mock(PagoService::class, function ($mock) {
        $mock->shouldReceive('revertirPago')
             ->once()
             ->andReturn(true);
    });

    $response = $this->withToken($saToken)
                     ->patchJson("/api/pagos/{$pago->id}/revertir");

    $response->assertOk()
             ->assertJson(['success' => true]);
});

// ─── Scenario 7: Asesor tries to revert pago → 403 ──────────────────────────

it('Asesor trying to revert pago gets 403', function () {
    [$user, $asesor, $token] = pagoTestMakeAsesor();
    $cuota = pagoTestMakeCuotaActiva(Grupo::factory()->create(['asesor_id' => $asesor->id]));
    $pago  = pagoTestInsertPago($cuota->id, 'aprobado');

    $response = $this->withToken($token)
                     ->patchJson("/api/pagos/{$pago->id}/revertir");

    $response->assertForbidden()
             ->assertJson(['success' => false]);
});
