<?php

use App\Contracts\RetanqueoWorkflowInterface;
use App\Domain\Grupos\MorosoSeparationService;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * SDD dominio-pagos-mora-retanqueo, Eje 5 — coordinación retanqueo ↔
 * separación. Decisión de negocio: separación bloquea retanqueo, nunca al
 * revés. `MorosoSeparationService` ejecuta atómicamente (sin estado
 * "pendiente" intermedio) — el punto de enforcement vive del lado de
 * retanqueo: `RetanqueoWorkflowService::aprobarRetanqueo()` /
 * `crearSolicitudRetanqueo()`.
 */
function crearRolesSpatieRetanqueoSeparacion(): void
{
    foreach (['super_admin', 'Jefe de creditos', 'Jefe de operaciones', 'Asesor'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
    }
}

/**
 * Grupo de 3 integrantes (adheridos al pivot grupo_cliente), con un préstamo
 * grupal 'Aprobado' y exactamente 1 CuotasGrupales pendiente con saldo — el
 * escenario base que exige `aprobarRetanqueo()` (Eje 1, check estructural de
 * "1 cuota pendiente").
 */
function setupGrupoDeTresParaRetanqueo(): array
{
    crearRolesSpatieRetanqueoSeparacion();

    $user = User::factory()->create();
    $grupo = Grupo::factory()->create(['numero_integrantes' => 3]);
    $clientes = Cliente::factory()->count(3)->create();

    foreach ($clientes as $cliente) {
        $grupo->clientes()->attach($cliente->id, ['fecha_ingreso' => now()]);
    }

    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => Prestamo::ESTADO_APROBADO,
        'tipo'     => 'grupal',
    ]);

    $cuotaGrupal = CuotasGrupales::factory()->create([
        'prestamo_id'        => $prestamo->id,
        'numero_cuota'       => 1,
        'monto_cuota_grupal' => 600.00,
        'estado_pago'        => 'pendiente',
        'fecha_vencimiento'  => now()->subDays(5),
    ]);

    return compact('user', 'grupo', 'clientes', 'prestamo', 'cuotaGrupal');
}

function participantesRetanqueoDeTres(\Illuminate\Support\Collection $clientes): array
{
    return [
        ['cliente_id' => $clientes[0]->id, 'participacion_tipo' => 'retanquea', 'monto_solicitado' => 500],
        ['cliente_id' => $clientes[1]->id, 'participacion_tipo' => 'retanquea', 'monto_solicitado' => 500],
        ['cliente_id' => $clientes[2]->id, 'participacion_tipo' => 'no_retanquea', 'monto_solicitado' => 0],
    ];
}

it('bloquea la aprobación cuando un integrante fue separado del grupo después de crearse la solicitud', function () {
    ['user' => $user, 'clientes' => $clientes, 'prestamo' => $prestamo] = setupGrupoDeTresParaRetanqueo();

    $workflow = app(RetanqueoWorkflowInterface::class);

    $retanqueo = $workflow->crearSolicitudRetanqueo($prestamo->id, participantesRetanqueoDeTres($clientes));

    // El cliente que NO retanquea (clientes[2]) tiene una cuota pendiente
    // vencida en el préstamo antiguo -> puede ser separado por mora.
    CuotaIndividual::factory()->vencida()->create([
        'prestamo_id'   => $prestamo->id,
        'cliente_id'    => $clientes[2]->id,
        'numero_cuota'  => 1,
        'saldo_capital' => 100.00,
        'saldo_interes' => 10.00,
    ]);

    // Separación requiere que el préstamo esté en un estado "activo" (no
    // 'Aprobado') — transición legítima ocurrida DESPUÉS de crear la solicitud.
    $prestamo->update(['estado' => Prestamo::ESTADO_ACTIVO]);

    (new MorosoSeparationService())->separar(
        $prestamo->id,
        $clientes[2]->id,
        $user->id,
        'Cliente inubicable, no responde llamadas'
    );

    expect(fn () => $workflow->aprobarRetanqueo($retanqueo->id))
        ->toThrow(\Exception::class, 'BLOQUEADA');

    expect($retanqueo->fresh()->estado_retanqueo)->toBe('solicitud_pendiente');
});

it('rechaza crearSolicitudRetanqueo cuando incluye un cliente ya separado de ese préstamo', function () {
    ['user' => $user, 'clientes' => $clientes, 'prestamo' => $prestamo] = setupGrupoDeTresParaRetanqueo();

    CuotaIndividual::factory()->vencida()->create([
        'prestamo_id'   => $prestamo->id,
        'cliente_id'    => $clientes[2]->id,
        'numero_cuota'  => 1,
        'saldo_capital' => 100.00,
        'saldo_interes' => 10.00,
    ]);

    $prestamo->update(['estado' => Prestamo::ESTADO_ACTIVO]);

    (new MorosoSeparationService())->separar(
        $prestamo->id,
        $clientes[2]->id,
        $user->id,
        'Cliente inubicable, no responde llamadas'
    );

    // Vuelve a 'Aprobado' para poder intentar crear otra solicitud sobre el
    // mismo préstamo (guard de defensa en profundidad — estructuralmente ya
    // debería ser imposible vía el pivot, pero se valida explícito).
    $prestamo->update(['estado' => Prestamo::ESTADO_APROBADO]);

    $workflow = app(RetanqueoWorkflowInterface::class);

    expect(fn () => $workflow->crearSolicitudRetanqueo($prestamo->id, participantesRetanqueoDeTres($clientes)))
        ->toThrow(\Exception::class, 'BLOQUEADA');
});

it('aprueba el retanqueo normalmente cuando no hubo separación (sin regresión)', function () {
    ['clientes' => $clientes, 'prestamo' => $prestamo] = setupGrupoDeTresParaRetanqueo();

    $workflow = app(RetanqueoWorkflowInterface::class);

    $retanqueo = $workflow->crearSolicitudRetanqueo($prestamo->id, participantesRetanqueoDeTres($clientes));

    $resultado = $workflow->aprobarRetanqueo($retanqueo->id);

    expect($resultado->estado_retanqueo)->toBe('aprobado');
});
