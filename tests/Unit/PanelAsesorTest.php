<?php

use App\Models\User;
use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\CuotasGrupales;
use App\Models\Mora;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Asesor', 'guard_name' => 'web']);

    // Usuario y rol
    $this->user = User::factory()->create();
    $this->user->assignRole('Asesor');

    $this->asesor = Asesor::factory()->create([
        'user_id' => $this->user->id,
        'estado_asesor' => 'activo',
    ]);

    // Cliente y grupo
    $this->cliente = Cliente::factory()->create([
        'asesor_id' => $this->asesor->id,
    ]);

    $this->grupo = Grupo::factory()->create([
        'nombre_grupo' => 'Grupo Estrella',
    ]);

    $this->grupo->clientes()->attach($this->cliente->id, [
        'fecha_ingreso' => now(),
        'rol' => 'miembro',
        'estado_grupo_cliente' => 'activo',
    ]);

    // Préstamo, cuota, pago y mora
    $this->prestamo = Prestamo::factory()->create([
        'grupo_id' => $this->grupo->id,
        'estado' => Prestamo::ESTADO_ACTIVO,
    ]);

    $this->cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $this->prestamo->id,
        'monto_cuota_grupal' => 300,
        'saldo_pendiente' => 100,
        'estado_cuota_grupal' => 'mora',
        'fecha_vencimiento' => now()->subDays(101)->toDateString(),
    ]);

    $this->pago = Pago::factory()->create([
        'cuota_grupal_id' => $this->cuota->id,
        'estado_pago' => 'Aprobado',
        'monto_pagado' => 300,
    ]);

    $this->mora = Mora::factory()->create([
        'cuota_grupal_id' => $this->cuota->id,
        'estado_mora' => 'pendiente',
    ]);
});

it('muestra el nombre del grupo en el panel del asesor', function () {
    $this->actingAs($this->user)
        ->get('/dashboard/asesor-page')
        ->assertOk()
        ->assertSee('GRUPO ESTRELLA');
});

it('muestra el monto de la cuota grupal en el panel del asesor', function () {
    $this->actingAs($this->user)
        ->get('/dashboard/asesor-page')
        ->assertSee('300');
});

it('muestra el saldo pendiente de la cuota en el panel del asesor', function () {
    $this->actingAs($this->user)
        ->get('/dashboard/asesor-page')
        ->assertSee((string) $this->cuota->saldo_pendiente);
});

it('muestra el monto del pago aprobado en el panel del asesor', function () {
    $this->actingAs($this->user)
        ->get('/dashboard/asesor-page')
        ->assertSee((string) $this->pago->monto_pagado);
});

it('muestra el estado de la mora en el panel del asesor', function () {
    $this->actingAs($this->user)
        ->get('/dashboard/asesor-page')
        ->assertSee('En mora');
});
