<?php

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeAsesorUserWithToken(): array
{
    $user   = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    $token  = $user->createToken('test')->plainTextToken;

    return [$user, $asesor, $token];
}

it('Asesor only sees own clients — cross-asesor isolation', function () {
    [$userA, $asesorA, $tokenA] = makeAsesorUserWithToken();
    [$userB, $asesorB, $tokenB] = makeAsesorUserWithToken();

    Cliente::factory()->count(3)->create(['asesor_id' => $asesorA->id]);
    Cliente::factory()->count(2)->create(['asesor_id' => $asesorB->id]);

    $response = $this->withToken($tokenA)->getJson('/api/clientes');

    $response->assertOk()
             ->assertJson(['success' => true]);

    $ids = collect($response->json('data'))->pluck('id')->all();
    $foreignIds = Cliente::where('asesor_id', $asesorB->id)->pluck('id')->all();

    expect(array_intersect($ids, $foreignIds))->toBeEmpty();
    expect(count($ids))->toBe(3);
});

it('Asesor can view own client without DNI in persona', function () {
    [$user, $asesor, $token] = makeAsesorUserWithToken();

    $cliente = Cliente::factory()->create(['asesor_id' => $asesor->id]);

    $response = $this->withToken($token)->getJson("/api/clientes/{$cliente->id}");

    $response->assertOk()
             ->assertJson(['success' => true]);

    // PII fields must NOT be present in persona for Asesor.
    // PersonaResource uses 'DNI' (uppercase) for the PII field.
    $persona = $response->json('data.persona');
    expect($persona)->not->toHaveKey('DNI');
    expect($persona)->not->toHaveKey('celular');
    expect($persona)->not->toHaveKey('correo');
    expect($persona)->not->toHaveKey('direccion');
});

it('super_admin can view client with DNI in persona', function () {
    $sa = User::factory()->create(['active' => true]);
    $sa->assignRole('super_admin');
    $token = $sa->createToken('test')->plainTextToken;

    $asesor  = Asesor::factory()->create();
    $cliente = Cliente::factory()->create(['asesor_id' => $asesor->id]);

    $response = $this->withToken($token)->getJson("/api/clientes/{$cliente->id}");

    $response->assertOk()
             ->assertJson(['success' => true]);

    $persona = $response->json('data.persona');
    // PersonaResource returns 'DNI' (uppercase) for roles that can see PII.
    expect($persona)->toHaveKey('DNI');
});

it('Asesor gets 404 when accessing another asesor\'s client', function () {
    [$userA, $asesorA, $tokenA] = makeAsesorUserWithToken();
    [$userB, $asesorB, $tokenB] = makeAsesorUserWithToken();

    $clienteB = Cliente::factory()->create(['asesor_id' => $asesorB->id]);

    $response = $this->withToken($tokenA)->getJson("/api/clientes/{$clienteB->id}");

    $response->assertNotFound()
             ->assertJson(['success' => false]);
});

it('returns 401 for unauthenticated requests to clientes', function () {
    $response = $this->getJson('/api/clientes');

    $response->assertUnauthorized()
             ->assertJson(['success' => false]);
});
