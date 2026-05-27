<?php

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Models\ProductoFinanciero;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function prestamoTestMakeAsesor(): array
{
    $user   = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    $token  = $user->createToken('test')->plainTextToken;

    return [$user, $asesor, $token];
}

function prestamoTestMakeJc(): array
{
    $user  = User::factory()->create(['active' => true]);
    $user->assignRole('Jefe de creditos');
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $token];
}

function prestamoTestMakeJo(): array
{
    $user  = User::factory()->create(['active' => true]);
    $user->assignRole('Jefe de operaciones');
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $token];
}

function prestamoTestMakePendiente(Grupo $grupo): Prestamo
{
    return Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_PENDIENTE,
    ]);
}

// ─── Scenario 1: Asesor creates prestamo for own group → 201, estado=Pendiente ──

it('Asesor creates prestamo for own group and gets 201 with estado Pendiente', function () {
    [$user, $asesor, $token] = prestamoTestMakeAsesor();
    $grupo   = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $cliente = Cliente::factory()->create();
    $producto = ProductoFinanciero::factory()->create();

    $payload = [
        'grupo_id'             => $grupo->id,
        'producto_id'          => $producto->id,
        'monto_prestado_total' => 1000.00,
        'cantidad_cuotas'      => 4,
        'frecuencia'           => 'Semanal',
        'fecha_prestamo'       => now()->toDateString(),
        'montos_individuales'  => [
            ['cliente_id' => $cliente->id, 'monto' => 1000.00],
        ],
    ];

    $response = $this->withToken($token)->postJson('/api/prestamos', $payload);

    $response->assertCreated()
             ->assertJson(['success' => true])
             ->assertJsonPath('data.estado', Prestamo::ESTADO_PENDIENTE);
});

// ─── Scenario 2: Asesor creates prestamo for foreign group → 422 ────────────

it('Asesor creating prestamo for foreign group gets 422', function () {
    [$userA, $asesorA, $tokenA] = prestamoTestMakeAsesor();
    [$userB, $asesorB, $tokenB] = prestamoTestMakeAsesor();

    $grupoB  = Grupo::factory()->create(['asesor_id' => $asesorB->id]);
    $cliente = Cliente::factory()->create();
    $producto = ProductoFinanciero::factory()->create();

    $payload = [
        'grupo_id'             => $grupoB->id,
        'producto_id'          => $producto->id,
        'monto_prestado_total' => 1000.00,
        'cantidad_cuotas'      => 4,
        'frecuencia'           => 'Semanal',
        'fecha_prestamo'       => now()->toDateString(),
        'montos_individuales'  => [
            ['cliente_id' => $cliente->id, 'monto' => 1000.00],
        ],
    ];

    $response = $this->withToken($tokenA)->postJson('/api/prestamos', $payload);

    $response->assertStatus(422)
             ->assertJson(['success' => false]);
});

// ─── Scenario 3: JC approves Pendiente prestamo → 200, estado=Aprobado ─────

it('JC approves Pendiente prestamo and gets 200 with estado Aprobado', function () {
    [, $asesor] = prestamoTestMakeAsesor();
    [$jcUser, $jcToken] = prestamoTestMakeJc();

    $grupo   = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = prestamoTestMakePendiente($grupo);

    $response = $this->withToken($jcToken)
                     ->patchJson("/api/prestamos/{$prestamo->id}/aprobar");

    $response->assertOk()
             ->assertJson(['success' => true])
             ->assertJsonPath('data.estado', Prestamo::ESTADO_APROBADO);

    expect($prestamo->fresh()->estado)->toBe(Prestamo::ESTADO_APROBADO);
});

// ─── Scenario 4: JC approves already-Aprobado prestamo → 422 ───────────────

it('JC approving already-Aprobado prestamo gets 422 (invalid FSM state)', function () {
    [, $asesor] = prestamoTestMakeAsesor();
    [$jcUser, $jcToken] = prestamoTestMakeJc();

    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_APROBADO,
    ]);

    $response = $this->withToken($jcToken)
                     ->patchJson("/api/prestamos/{$prestamo->id}/aprobar");

    $response->assertStatus(422)
             ->assertJson(['success' => false]);
});

// ─── Scenario 5: Asesor tries to approve → 403 ──────────────────────────────

it('Asesor trying to approve prestamo gets 403', function () {
    [$asesorUser, $asesor, $asesorToken] = prestamoTestMakeAsesor();

    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = prestamoTestMakePendiente($grupo);

    $response = $this->withToken($asesorToken)
                     ->patchJson("/api/prestamos/{$prestamo->id}/aprobar");

    $response->assertForbidden()
             ->assertJson(['success' => false]);
});

// ─── Scenario 6: JC rejects with motivo → 200, estado=Rechazado ─────────────

it('JC rejects prestamo with motivo and gets 200 with estado Rechazado', function () {
    [, $asesor] = prestamoTestMakeAsesor();
    [$jcUser, $jcToken] = prestamoTestMakeJc();

    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = prestamoTestMakePendiente($grupo);

    $motivo = 'Documentación incompleta';

    $response = $this->withToken($jcToken)
                     ->patchJson("/api/prestamos/{$prestamo->id}/rechazar", [
                         'motivo' => $motivo,
                     ]);

    $response->assertOk()
             ->assertJson(['success' => true])
             ->assertJsonPath('data.estado', Prestamo::ESTADO_RECHAZADO);

    expect($prestamo->fresh()->descripcion)->toBe($motivo);
});

// ─── Scenario 7: Asesor firma Aprobado prestamo → 200, estado=Firmado ───────

it('Asesor signs Aprobado prestamo and gets 200 with estado Firmado', function () {
    [$asesorUser, $asesor, $asesorToken] = prestamoTestMakeAsesor();

    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_APROBADO,
    ]);

    $response = $this->withToken($asesorToken)
                     ->patchJson("/api/prestamos/{$prestamo->id}/firmar");

    $response->assertOk()
             ->assertJson(['success' => true])
             ->assertJsonPath('data.estado', Prestamo::ESTADO_FIRMADO);

    expect($prestamo->fresh()->estado)->toBe(Prestamo::ESTADO_FIRMADO);
});

// ─── Scenario 8: JO disburses Firmado prestamo → 200, estado=Activo ─────────

it('JO disburses Firmado prestamo and gets 200 with estado Activo and account fields set', function () {
    [, $asesor] = prestamoTestMakeAsesor();
    [$joUser, $joToken] = prestamoTestMakeJo();

    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_FIRMADO,
    ]);

    $response = $this->withToken($joToken)
                     ->patchJson("/api/prestamos/{$prestamo->id}/desembolsar", [
                         'titular_cuenta' => 'Juan Perez',
                         'numero_cuenta'  => '012-345678',
                     ]);

    $response->assertOk()
             ->assertJson(['success' => true])
             ->assertJsonPath('data.estado', Prestamo::ESTADO_ACTIVO);

    $fresh = $prestamo->fresh();
    expect($fresh->estado)->toBe(Prestamo::ESTADO_ACTIVO);
    expect($fresh->titular_cuenta_desembolso)->toBe('Juan Perez');
    expect($fresh->numero_cuenta_desembolso)->toBe('012-345678');
});
