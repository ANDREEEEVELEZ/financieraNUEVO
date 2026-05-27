<?php

use App\Http\Resources\Api\ClienteResource;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Http\Request;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Helper: build a ClienteResource array as the given authenticated user would see it.
 */
function clienteResourceAs(User $viewer, Cliente $cliente): array
{
    auth()->login($viewer);

    $resource = new ClienteResource($cliente);
    $request  = Request::create('/api/clientes', 'GET');
    $request->setUserResolver(fn () => $viewer);

    return $resource->resolve($request);
}

it('includes asesor_id in response', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');

    $cliente = Cliente::factory()->create();

    $data = clienteResourceAs($user, $cliente);

    expect($data)->toHaveKey('asesor_id');
    expect($data['asesor_id'])->toEqual($cliente->asesor_id);
});

it('includes condicion_personal in response (nullable)', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');

    $cliente = Cliente::factory()->create(['condicion_personal' => 'PEP']);

    $data = clienteResourceAs($user, $cliente);

    expect($data)->toHaveKey('condicion_personal', 'PEP');
});

it('includes condicion_personal key regardless of value', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');

    // condicion_personal is nullable in DB but the mutator coerces null→'' via strtoupper(null).
    // The resource projects whatever the model stores — key must be present.
    $cliente = Cliente::factory()->create(['condicion_personal' => 'Iletrado']);

    $data = clienteResourceAs($user, $cliente);

    expect($data)->toHaveKey('condicion_personal');
});

it('grupos key is absent when relation not loaded', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');

    $cliente = Cliente::factory()->create();

    $data = clienteResourceAs($user, $cliente);

    expect($data)->not->toHaveKey('grupos');
});

it('prestamos key is absent when relation not loaded', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');

    $cliente = Cliente::factory()->create();

    $data = clienteResourceAs($user, $cliente);

    expect($data)->not->toHaveKey('prestamos');
});

it('scoring block includes both grado and score when relation loaded', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');

    $cliente = Cliente::factory()->create();

    $scoring = new \stdClass();
    $scoring->grado = 'A';
    $scoring->score = 85;

    $cliente->setRelation('scoring', $scoring);

    $data = clienteResourceAs($user, $cliente);

    expect($data)->toHaveKey('scoring');
    expect($data['scoring'])->toHaveKey('grado', 'A');
    expect($data['scoring'])->toHaveKey('score', 85);
});
