<?php

use App\Models\Asesor;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeAsesorActorWithToken(): array
{
    $user   = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    $token  = $user->createToken('test')->plainTextToken;

    return [$user, $asesor, $token];
}

// ─── Scenario 1: Asesor lists prestamos — only sees own grupos' prestamos ───

it('Asesor only sees prestamos belonging to their own grupos', function () {
    [$userA, $asesorA, $tokenA] = makeAsesorActorWithToken();
    [$userB, $asesorB, $tokenB] = makeAsesorActorWithToken();

    $grupoA = Grupo::factory()->create(['asesor_id' => $asesorA->id]);
    $grupoB = Grupo::factory()->create(['asesor_id' => $asesorB->id]);

    $prestamosA = Prestamo::factory()->count(2)->create([
        'grupo_id' => $grupoA->id,
        'estado'   => Prestamo::ESTADO_PENDIENTE,
    ]);
    Prestamo::factory()->count(2)->create([
        'grupo_id' => $grupoB->id,
        'estado'   => Prestamo::ESTADO_PENDIENTE,
    ]);

    $response = $this->withToken($tokenA)->getJson('/api/prestamos');

    $response->assertOk()->assertJson(['success' => true]);

    $returnedIds = collect($response->json('data'))->pluck('id')->all();
    $ownIds      = $prestamosA->pluck('id')->all();
    $foreignIds  = Prestamo::whereHas('grupo', fn ($q) => $q->where('asesor_id', $asesorB->id))
                       ->pluck('id')->all();

    // Only own prestamos returned
    expect(array_intersect($returnedIds, $foreignIds))->toBeEmpty();
    expect(count(array_intersect($returnedIds, $ownIds)))->toBe(2);
});

// ─── Scenario 2: Asesor show other asesor's prestamo → 404 ──────────────────

it('Asesor gets 404 when requesting another asesor\'s prestamo', function () {
    [$userA, $asesorA, $tokenA] = makeAsesorActorWithToken();
    [$userB, $asesorB, $tokenB] = makeAsesorActorWithToken();

    $grupoB   = Grupo::factory()->create(['asesor_id' => $asesorB->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupoB->id,
        'estado'   => Prestamo::ESTADO_PENDIENTE,
    ]);

    $response = $this->withToken($tokenA)->getJson("/api/prestamos/{$prestamo->id}");

    $response->assertNotFound()
             ->assertJson(['success' => false]);
});

// ─── Scenario 3: SA sees all prestamos ──────────────────────────────────────

it('super_admin can list all prestamos across all asesores', function () {
    $sa = User::factory()->create(['active' => true]);
    $sa->assignRole('super_admin');
    $saToken = $sa->createToken('test')->plainTextToken;

    $asesorA = Asesor::factory()->create();
    $asesorB = Asesor::factory()->create();

    $grupoA = Grupo::factory()->create(['asesor_id' => $asesorA->id]);
    $grupoB = Grupo::factory()->create(['asesor_id' => $asesorB->id]);

    Prestamo::factory()->count(2)->create([
        'grupo_id' => $grupoA->id,
        'estado'   => Prestamo::ESTADO_PENDIENTE,
    ]);
    Prestamo::factory()->count(2)->create([
        'grupo_id' => $grupoB->id,
        'estado'   => Prestamo::ESTADO_PENDIENTE,
    ]);

    $response = $this->withToken($saToken)->getJson('/api/prestamos');

    $response->assertOk()->assertJson(['success' => true]);

    expect(count($response->json('data')))->toBeGreaterThanOrEqual(4);
});

// ─── Scenario 4: JC sees all prestamos ──────────────────────────────────────

it('Jefe de creditos can list all prestamos', function () {
    $jc = User::factory()->create(['active' => true]);
    $jc->assignRole('Jefe de creditos');
    $jcToken = $jc->createToken('test')->plainTextToken;

    $asesorA = Asesor::factory()->create();
    $asesorB = Asesor::factory()->create();

    $grupoA = Grupo::factory()->create(['asesor_id' => $asesorA->id]);
    $grupoB = Grupo::factory()->create(['asesor_id' => $asesorB->id]);

    Prestamo::factory()->count(2)->create([
        'grupo_id' => $grupoA->id,
        'estado'   => Prestamo::ESTADO_PENDIENTE,
    ]);
    Prestamo::factory()->count(2)->create([
        'grupo_id' => $grupoB->id,
        'estado'   => Prestamo::ESTADO_PENDIENTE,
    ]);

    $response = $this->withToken($jcToken)->getJson('/api/prestamos');

    $response->assertOk()->assertJson(['success' => true]);

    expect(count($response->json('data')))->toBeGreaterThanOrEqual(4);
});
