<?php

use App\Models\Asesor;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['super_admin', 'Jefe de creditos', 'Jefe de operaciones', 'Asesor'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
    }

    $this->asesor1User = User::factory()->create(['active' => true]);
    $this->asesor1     = Asesor::factory()->create(['user_id' => $this->asesor1User->id, 'estado_asesor' => 'Activo']);
    $this->asesor1User->assignRole('Asesor');

    $this->asesor2User = User::factory()->create(['active' => true]);
    $this->asesor2     = Asesor::factory()->create(['user_id' => $this->asesor2User->id, 'estado_asesor' => 'Activo']);
    $this->asesor2User->assignRole('Asesor');

    $this->grupo = Grupo::factory()->create(['asesor_id' => $this->asesor1->id]);

    Cache::put('ec_asesor_user_' . $this->asesor1User->id, $this->asesor1, 300);
    Cache::put('ec_asesor_user_' . $this->asesor2User->id, $this->asesor2, 300);
});

it('asesor cannot access another asesor grupo contracts — IDOR blocked', function () {
    $this->actingAs($this->asesor2User)
        ->get("/contratos/grupo/{$this->grupo->id}")
        ->assertForbidden();
});

it('asesor can access own grupo contracts', function () {
    $response = $this->actingAs($this->asesor1User)
        ->get("/contratos/grupo/{$this->grupo->id}");

    expect($response->status())->not->toBe(403);
});

it('super_admin can access any grupo contracts', function () {
    $admin = User::factory()->create(['active' => true]);
    $admin->assignRole('super_admin');

    $response = $this->actingAs($admin)->get("/contratos/grupo/{$this->grupo->id}");

    expect($response->status())->not->toBe(403);
});

it('asesor cannot access another asesor prestamo contracts — IDOR blocked', function () {
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $this->grupo->id,
        'estado'   => Prestamo::ESTADO_ACTIVO,
    ]);

    $this->actingAs($this->asesor2User)
        ->get("/contratos/prestamo/{$prestamo->id}")
        ->assertForbidden();
});

it('asesor cannot access another asesor cartilla — IDOR blocked', function () {
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $this->grupo->id,
        'estado'   => Prestamo::ESTADO_ACTIVO,
    ]);

    $this->actingAs($this->asesor2User)
        ->get("/cartilla/prestamo/{$prestamo->id}")
        ->assertForbidden();
});
