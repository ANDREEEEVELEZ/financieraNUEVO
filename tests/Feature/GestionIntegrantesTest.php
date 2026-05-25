<?php

use App\Models\Grupo;
use App\Models\Cliente;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crearGrupoConClientes(int $cantidad = 5): array
{
    $grupo = Grupo::factory()->create(['estado_grupo' => 'Activo']);
    $clientes = [];

    for ($i = 0; $i < $cantidad; $i++) {
        $cliente = Cliente::factory()->create();
        $grupo->clientes()->attach($cliente->id, [
            'fecha_ingreso' => now(),
            'estado_grupo_cliente' => 'Activo',
        ]);
        $clientes[] = $cliente;
    }

    return [$grupo, $clientes];
}

it('puede remover integrante sin prestamos', function () {
    [$grupo, $clientes] = crearGrupoConClientes(5);
    $clienteARemover = $clientes[0];

    expect($grupo->clientes()->where('cliente_id', $clienteARemover->id)->exists())->toBeTrue();

    $resultado = $grupo->removerCliente($clienteARemover->id);

    expect($resultado)->toBeTrue();
    expect($grupo->clientes()->where('cliente_id', $clienteARemover->id)->exists())->toBeFalse();
    expect($grupo->exIntegrantes()->where('cliente_id', $clienteARemover->id)->exists())->toBeTrue();
});

it('no puede remover integrante con prestamos activos', function () {
    [$grupo, $clientes] = crearGrupoConClientes(5);
    $clienteARemover = $clientes[0];

    Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => 'Aprobado',
    ]);

    expect(fn () => $grupo->removerCliente($clienteARemover->id))
        ->toThrow(\Exception::class, 'No se puede remover integrantes de un grupo con préstamos activos.');
});

it('puede transferir integrante entre grupos sin prestamos', function () {
    [$grupoOrigen, $clientes] = crearGrupoConClientes(5);
    $grupoDestino = Grupo::factory()->create(['estado_grupo' => 'Activo']);
    $clienteATransferir = $clientes[0];

    $resultado = $grupoOrigen->transferirClienteAGrupo($clienteATransferir->id, $grupoDestino->id);

    expect($resultado)->toBeTrue();
    expect($grupoOrigen->clientes()->where('cliente_id', $clienteATransferir->id)->exists())->toBeFalse();
    expect($grupoOrigen->exIntegrantes()->where('cliente_id', $clienteATransferir->id)->exists())->toBeTrue();
    expect($grupoDestino->clientes()->where('cliente_id', $clienteATransferir->id)->exists())->toBeTrue();
});

it('detecta prestamos activos correctamente', function () {
    $grupo = Grupo::factory()->create(['estado_grupo' => 'Activo']);

    expect($grupo->tienePrestamosActivos())->toBeFalse();

    Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'Pendiente']);
    expect($grupo->tienePrestamosActivos())->toBeTrue();

    $grupo->prestamos()->delete();
    Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'Aprobado']);
    expect($grupo->tienePrestamosActivos())->toBeTrue();

    $grupo->prestamos()->delete();
    Prestamo::factory()->create(['grupo_id' => $grupo->id, 'estado' => 'Finalizado']);
    expect($grupo->tienePrestamosActivos())->toBeFalse();
});
