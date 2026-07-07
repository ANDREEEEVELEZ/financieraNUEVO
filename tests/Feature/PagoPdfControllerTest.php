<?php

use App\Models\Asesor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['super_admin', 'Jefe de creditos', 'Jefe de operaciones', 'Asesor'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
    }
    Cache::flush();
});

it('exportar returns 403 envelope for Asesor without linked Asesor record, no PDF streamed', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');

    $response = $this->actingAs($user)->get('/pagos/exportar/pdf');

    $response->assertForbidden()
        ->assertJson(['success' => false]);

    expect($response->headers->get('content-type'))->not->toContain('application/pdf');
});

it('exportar streams a PDF for Asesor with linked Asesor record', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    Asesor::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/pagos/exportar/pdf');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('exportar is unaffected for super_admin role and returns a PDF', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('super_admin');

    $response = $this->actingAs($user)->get('/pagos/exportar/pdf');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
