<?php

use App\Models\Asesor;
use App\Models\Grupo;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeAsesorWithToken(): array
{
    $user  = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    $token  = $user->createToken('test')->plainTextToken;

    return [$user, $asesor, $token];
}

it('Asesor only sees own groups — cross-asesor isolation', function () {
    [$userA, $asesorA, $tokenA] = makeAsesorWithToken();
    [$userB, $asesorB, $tokenB] = makeAsesorWithToken();

    // Create 2 groups for A and 1 for B
    Grupo::factory()->count(2)->create(['asesor_id' => $asesorA->id]);
    Grupo::factory()->count(1)->create(['asesor_id' => $asesorB->id]);

    $response = $this->withToken($tokenA)->getJson('/api/grupos');

    $response->assertOk()
             ->assertJson(['success' => true]);

    $ids = collect($response->json('data'))->pluck('id')->all();
    $foreignIds = Grupo::where('asesor_id', $asesorB->id)->pluck('id')->all();

    expect(array_intersect($ids, $foreignIds))->toBeEmpty();
    expect(count($ids))->toBe(2);
});

it('Asesor can view their own group detail with integrantes', function () {
    [$user, $asesor, $token] = makeAsesorWithToken();

    $grupo = Grupo::factory()->create(['asesor_id' => $asesor->id]);

    $response = $this->withToken($token)->getJson("/api/grupos/{$grupo->id}");

    $response->assertOk()
             ->assertJson(['success' => true])
             ->assertJsonStructure([
                 'data' => ['id', 'nombre_grupo', 'integrantes'],
             ]);
});

it('Asesor gets 404 when accessing another asesor\'s group', function () {
    [$userA, $asesorA, $tokenA] = makeAsesorWithToken();
    [$userB, $asesorB, $tokenB] = makeAsesorWithToken();

    $grupoB = Grupo::factory()->create(['asesor_id' => $asesorB->id]);

    $response = $this->withToken($tokenA)->getJson("/api/grupos/{$grupoB->id}");

    $response->assertNotFound()
             ->assertJson(['success' => false]);
});

it('super_admin can list all groups', function () {
    $sa = User::factory()->create(['active' => true]);
    $sa->assignRole('super_admin');
    $token = $sa->createToken('test')->plainTextToken;

    // Create groups for two different asesores
    $asesorA = Asesor::factory()->create();
    $asesorB = Asesor::factory()->create();
    Grupo::factory()->count(2)->create(['asesor_id' => $asesorA->id]);
    Grupo::factory()->count(2)->create(['asesor_id' => $asesorB->id]);

    $response = $this->withToken($token)->getJson('/api/grupos');

    $response->assertOk()
             ->assertJson(['success' => true]);

    expect(count($response->json('data')))->toBeGreaterThanOrEqual(4);
});

it('returns 401 for unauthenticated requests to grupos', function () {
    $response = $this->getJson('/api/grupos');

    $response->assertUnauthorized()
             ->assertJson(['success' => false]);
});
