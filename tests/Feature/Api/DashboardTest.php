<?php

use App\Models\Asesor;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeDashboardUser(string $role): array
{
    $user = User::factory()->create(['active' => true]);
    $user->assignRole($role);

    if ($role === 'Asesor') {
        Asesor::factory()->create(['user_id' => $user->id]);
    }

    $token = $user->createToken('test')->plainTextToken;

    return [$user, $token];
}

it('returns 200 with all KPI keys for an Asesor', function () {
    [$user, $token] = makeDashboardUser('Asesor');

    $response = $this->withToken($token)->getJson('/api/dashboard');

    $response->assertOk()
             ->assertJson(['success' => true])
             ->assertJsonStructure([
                 'data' => [
                     'cartera_total',
                     'grupos_count',
                     'clientes_count',
                     'cuotas_hoy',
                     'cuotas_mora',
                     'meta_mensual' => ['monto', 'cobrado', 'porcentaje'],
                 ],
             ]);
});

it('returns 200 for super_admin', function () {
    [$user, $token] = makeDashboardUser('super_admin');

    $response = $this->withToken($token)->getJson('/api/dashboard');

    $response->assertOk()
             ->assertJson(['success' => true]);
});

it('returns 403 for Jefe de creditos', function () {
    [$user, $token] = makeDashboardUser('Jefe de creditos');

    $response = $this->withToken($token)->getJson('/api/dashboard');

    $response->assertForbidden()
             ->assertJson(['success' => false]);
});

it('returns 403 for Jefe de operaciones', function () {
    [$user, $token] = makeDashboardUser('Jefe de operaciones');

    $response = $this->withToken($token)->getJson('/api/dashboard');

    $response->assertForbidden()
             ->assertJson(['success' => false]);
});

it('returns 401 for unauthenticated requests', function () {
    $response = $this->getJson('/api/dashboard');

    $response->assertUnauthorized()
             ->assertJson(['success' => false]);
});
