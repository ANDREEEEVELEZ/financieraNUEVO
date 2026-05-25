<?php

use App\Models\User;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Models\Grupo;
use App\Models\Cliente;
use App\Models\Asesor;
use App\Models\Persona;
use App\Models\CuotasGrupales;
use App\Models\CuotaIndividual;
use App\Models\MovimientoFinanciero;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['super_admin', 'Jefe de creditos', 'Jefe de operaciones', 'Asesor'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    foreach (['prestamos.aprobar', 'prestamos.rechazar', 'prestamos.reducir_monto', 'prestamos.desembolsar', 'prestamos.crear', 'prestamos.ver_todos'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    Role::findByName('Jefe de creditos')->givePermissionTo(['prestamos.aprobar', 'prestamos.rechazar', 'prestamos.reducir_monto']);
    Role::findByName('Jefe de operaciones')->givePermissionTo(['prestamos.reducir_monto', 'prestamos.desembolsar']);
    Role::findByName('Asesor')->givePermissionTo(['prestamos.crear']);

    $this->superAdmin = User::factory()->create(['name' => 'Super Admin']);
    $this->superAdmin->assignRole('super_admin');

    $this->jefeCreditos = User::factory()->create(['name' => 'Jefe Creditos']);
    $this->jefeCreditos->assignRole('Jefe de creditos');

    $this->jefeOperaciones = User::factory()->create(['name' => 'Jefe Operaciones']);
    $this->jefeOperaciones->assignRole('Jefe de operaciones');

    $this->asesor = User::factory()->create(['name' => 'Asesor Test']);
    $this->asesor->assignRole('Asesor');

    $personaAsesor = Persona::factory()->create();
    $this->asesorRecord = Asesor::factory()->create([
        'persona_id'    => $personaAsesor->id,
        'user_id'       => $this->asesor->id,
        'estado_asesor' => 'ACTIVO',
    ]);

    $this->grupo = Grupo::factory()->create([
        'asesor_id'    => $this->asesorRecord->id,
        'estado_grupo' => 'ACTIVO',
    ]);

    $clientes = Cliente::factory()->count(3)->create(['asesor_id' => $this->asesorRecord->id]);
    $this->grupo->clientes()->attach($clientes->pluck('id'));

    $this->prestamo = Prestamo::factory()->create([
        'grupo_id'             => $this->grupo->id,
        'estado'               => Prestamo::ESTADO_PENDIENTE,
        'monto_prestado_total' => 4000,
        'monto_devolver'       => 4680,
        'tasa_interes'         => 17,
        'cantidad_cuotas'      => 4,
        'frecuencia'           => 'mensual',
        'fecha_prestamo'       => now()->toDateString(),
    ]);

    $montos = [1000, 1500, 1500];
    foreach ($clientes as $index => $cliente) {
        $monto = $montos[$index];
        PrestamoIndividual::factory()->create([
            'prestamo_id'                    => $this->prestamo->id,
            'cliente_id'                     => $cliente->id,
            'monto_prestado_individual'      => $monto,
            'monto_cuota_prestamo_individual' => round(($monto * 1.17) / 4, 2),
            'monto_devolver_individual'      => round($monto * 1.17, 2),
            'interes'                        => round($monto * 0.17, 2),
            'seguro'                         => 0,
            'estado'                         => 'Pendiente',
        ]);
    }
});

// ========================================
// Flujo de estados
// ========================================

it('flujo pendiente a aprobado', function () {
    expect($this->prestamo->estado)->toBe(Prestamo::ESTADO_PENDIENTE);

    $this->actingAs($this->jefeCreditos);
    $this->prestamo->aprobar();
    $this->prestamo->refresh();

    expect($this->prestamo->estado)->toBe(Prestamo::ESTADO_APROBADO);
});

it('flujo aprobado a firmado', function () {
    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_APROBADO]);

    $this->actingAs($this->asesor);
    $this->prestamo->firmar();
    $this->prestamo->refresh();

    expect($this->prestamo->estado)->toBe(Prestamo::ESTADO_FIRMADO);
});

it('flujo firmado a activo', function () {
    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_FIRMADO]);

    $this->actingAs($this->jefeOperaciones);
    $fechaDesembolso = now();
    $this->prestamo->desembolsar($fechaDesembolso->toDateString());
    $this->prestamo->refresh();

    expect($this->prestamo->estado)->toBe(Prestamo::ESTADO_ACTIVO);
    expect($this->prestamo->fecha_desembolso->toDateString())->toBe($fechaDesembolso->toDateString());
});

it('flujo completo pendiente a activo', function () {
    expect($this->prestamo->estado)->toBe(Prestamo::ESTADO_PENDIENTE);

    $this->prestamo->aprobar();
    $this->prestamo->refresh();
    expect($this->prestamo->estado)->toBe(Prestamo::ESTADO_APROBADO);

    $this->prestamo->firmar();
    $this->prestamo->refresh();
    expect($this->prestamo->estado)->toBe(Prestamo::ESTADO_FIRMADO);

    $this->prestamo->desembolsar(now()->toDateString());
    $this->prestamo->refresh();
    expect($this->prestamo->estado)->toBe(Prestamo::ESTADO_ACTIVO);
});

// ========================================
// Reducción de monto (requiere APROBADO o FIRMADO)
// ========================================

it('reducir monto actualiza proporcionalmente', function () {
    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_APROBADO]);
    $this->prestamo->refresh();

    $nuevoMonto = 3000.0;
    $resultado = $this->prestamo->reducirMonto($nuevoMonto, 'Cliente solicitó menos dinero');

    expect($resultado)->toBeTrue();
    expect((float) $this->prestamo->fresh()->monto_prestado_total)->toEqualWithDelta($nuevoMonto, 0.01);
});

it('no se puede reducir monto a mayor', function () {
    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_APROBADO]);
    $this->prestamo->refresh();

    $montoMayor = (float) $this->prestamo->monto_prestado_total + 1000;

    $resultado = $this->prestamo->reducirMonto($montoMayor, 'Intento de aumento');
    expect($resultado)->toBeFalse();
});

it('no se puede reducir monto en estado pendiente', function () {
    expect($this->prestamo->estado)->toBe(Prestamo::ESTADO_PENDIENTE);
    expect($this->prestamo->puedeReducirMonto())->toBeFalse();
});

// ========================================
// Cuotas
// ========================================

it('cuotas no se crean al aprobar', function () {
    $cuotasAntes = CuotasGrupales::where('prestamo_id', $this->prestamo->id)->count();

    $this->prestamo->aprobar();
    $this->prestamo->refresh();

    expect($this->prestamo->estado)->toBe(Prestamo::ESTADO_APROBADO);
    expect(CuotasGrupales::where('prestamo_id', $this->prestamo->id)->count())->toBe($cuotasAntes);
});

it('cuotas si se crean al desembolsar', function () {
    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_FIRMADO]);
    $cuotasAntes = CuotasGrupales::where('prestamo_id', $this->prestamo->id)->count();

    $this->prestamo->desembolsar(now()->toDateString());
    $this->prestamo->refresh();

    expect($this->prestamo->estado)->toBe(Prestamo::ESTADO_ACTIVO);
    expect(CuotasGrupales::where('prestamo_id', $this->prestamo->id)->count())->toBeGreaterThan($cuotasAntes);
});

// ========================================
// Permisos por rol
// ========================================

it('jc puede aprobar', function () {
    expect($this->jefeCreditos->can('prestamos.aprobar'))->toBeTrue();
});

it('jc puede reducir monto', function () {
    expect($this->jefeCreditos->can('prestamos.reducir_monto'))->toBeTrue();
});

it('jc no puede desembolsar', function () {
    expect($this->jefeCreditos->can('prestamos.desembolsar'))->toBeFalse();
});

it('jo puede desembolsar', function () {
    expect($this->jefeOperaciones->can('prestamos.desembolsar'))->toBeTrue();
});

it('jo puede reducir monto', function () {
    expect($this->jefeOperaciones->can('prestamos.reducir_monto'))->toBeTrue();
});

it('jo no puede aprobar', function () {
    expect($this->jefeOperaciones->can('prestamos.aprobar'))->toBeFalse();
});

it('asesor solo puede crear prestamos', function () {
    expect($this->asesor->can('prestamos.crear'))->toBeTrue();
    expect($this->asesor->can('prestamos.aprobar'))->toBeFalse();
    expect($this->asesor->can('prestamos.desembolsar'))->toBeFalse();
    expect($this->asesor->can('prestamos.reducir_monto'))->toBeFalse();
});

// ========================================
// MovimientoFinanciero (usa referencia_tipo/referencia_id/concepto)
// ========================================

it('movimiento financiero se registra al desembolsar', function () {
    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_FIRMADO]);
    $movimientosAntes = MovimientoFinanciero::where('referencia_tipo', Prestamo::class)
        ->where('referencia_id', $this->prestamo->id)
        ->count();

    $this->prestamo->desembolsar(now()->toDateString());

    expect(MovimientoFinanciero::where('referencia_tipo', Prestamo::class)
        ->where('referencia_id', $this->prestamo->id)
        ->count())->toBeGreaterThan($movimientosAntes);
});

it('movimiento financiero tiene concepto desembolso', function () {
    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_FIRMADO]);
    $this->prestamo->desembolsar(now()->toDateString());

    $movimiento = MovimientoFinanciero::where('referencia_tipo', Prestamo::class)
        ->where('referencia_id', $this->prestamo->id)
        ->where('concepto', 'desembolso')
        ->first();

    expect($movimiento)->not->toBeNull();
    expect($movimiento->concepto)->toBe('desembolso');
});

// ========================================
// Validaciones de estado (usando updateQuietly para evitar observer)
// ========================================

it('puede aprobar solo en estado pendiente', function () {
    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_PENDIENTE]);
    expect($this->prestamo->fresh()->puedeSerAprobado())->toBeTrue();

    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_APROBADO]);
    expect($this->prestamo->fresh()->puedeSerAprobado())->toBeFalse();

    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_ACTIVO]);
    expect($this->prestamo->fresh()->puedeSerAprobado())->toBeFalse();
});

it('puede desembolsar solo en estado firmado', function () {
    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_PENDIENTE]);
    expect($this->prestamo->fresh()->puedeDesembolsar())->toBeFalse();

    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_FIRMADO]);
    expect($this->prestamo->fresh()->puedeDesembolsar())->toBeTrue();

    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_ACTIVO]);
    expect($this->prestamo->fresh()->puedeDesembolsar())->toBeFalse();
});

it('puede reducir monto en aprobado o firmado', function () {
    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_PENDIENTE]);
    expect($this->prestamo->fresh()->puedeReducirMonto())->toBeFalse();

    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_APROBADO]);
    expect($this->prestamo->fresh()->puedeReducirMonto())->toBeTrue();

    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_FIRMADO]);
    expect($this->prestamo->fresh()->puedeReducirMonto())->toBeTrue();

    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_ACTIVO]);
    expect($this->prestamo->fresh()->puedeReducirMonto())->toBeFalse();
});

it('puede rechazar solo en pendiente', function () {
    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_PENDIENTE]);
    expect($this->prestamo->fresh()->puedeSerRechazado())->toBeTrue();

    $this->prestamo->updateQuietly(['estado' => Prestamo::ESTADO_APROBADO]);
    expect($this->prestamo->fresh()->puedeSerRechazado())->toBeFalse();
});
