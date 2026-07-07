<?php

use App\Models\Asesor;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function cuotaControllerTestMakeOrphanedAsesor(): array
{
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $token];
}

function cuotaControllerTestMakeLinkedAsesor(): array
{
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $asesor, $token];
}

// ─── index() ────────────────────────────────────────────────────────────────

it('CuotaController index returns 403 envelope for Asesor without linked Asesor record', function () {
    [$user, $token] = cuotaControllerTestMakeOrphanedAsesor();

    $response = $this->withToken($token)->getJson('/api/v1/cuotas');

    $response->assertForbidden()
        ->assertJson(['success' => false])
        ->assertJsonMissingPath('data.data');
});

it('CuotaController index returns 200 scoped data for Asesor with linked Asesor record', function () {
    [$user, $asesor, $token] = cuotaControllerTestMakeLinkedAsesor();

    $response = $this->withToken($token)->getJson('/api/v1/cuotas');

    $response->assertOk()->assertJson(['success' => true]);
});

it('CuotaController index is unaffected for super_admin role', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('super_admin');
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/cuotas');

    $response->assertOk()->assertJson(['success' => true]);
});

// ─── hoy() ──────────────────────────────────────────────────────────────────

it('CuotaController hoy returns 403 envelope for Asesor without linked Asesor record', function () {
    [$user, $token] = cuotaControllerTestMakeOrphanedAsesor();

    $response = $this->withToken($token)->getJson('/api/v1/cuotas/hoy');

    $response->assertForbidden()
        ->assertJson(['success' => false]);
});

it('CuotaController hoy returns 200 scoped data for Asesor with linked Asesor record', function () {
    [$user, $asesor, $token] = cuotaControllerTestMakeLinkedAsesor();

    $response = $this->withToken($token)->getJson('/api/v1/cuotas/hoy');

    $response->assertOk()->assertJson(['success' => true]);
});

it('CuotaController hoy is unaffected for Jefe de operaciones role', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Jefe de operaciones');
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/cuotas/hoy');

    $response->assertOk()->assertJson(['success' => true]);
});
